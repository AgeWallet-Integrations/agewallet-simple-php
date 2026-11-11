<?php

/**
 * PHP's built-in web server (php -S) on a free 127.0.0.1 port, serving the repo root, for code that ends in header()
 * and exit or makes HTTP requests. Its scripts get AW_TEST_DIR, a fresh temp directory shared with the test through
 * file(); the scripts in tests/fixtures/ only read and write files there. display_errors is off, so a fatal error
 * answers HTTP 500.
 *
 *   $server = BuiltInServer::start();
 *   $response = $server->request('/agewallet-client.php?action=check'); // ['status' => 200, 'headers' => [...], 'body' => '0']
 *   $server->stop();
 */
final class BuiltInServer
{
    /** @var resource */
    private $process;
    /** @var int */
    private $port;
    /** @var string */
    private $dir;

    public static function start(): self
    {
        $server = new self();

        $probe = stream_socket_server('tcp://127.0.0.1:0');
        $name = stream_socket_get_name($probe, false);
        fclose($probe);
        $server->port = (int) substr($name, strrpos($name, ':') + 1);

        $server->dir = sys_get_temp_dir() . '/aw-server-' . $server->port . '-' . bin2hex(random_bytes(4));
        mkdir($server->dir . '/sessions', 0700, true);

        $command = [
            PHP_BINARY,
            '-d', 'display_errors=0',
            '-d', 'log_errors=1',
            '-d', 'error_log=' . $server->dir . '/server.log',
            '-d', 'session.save_path=' . $server->dir . '/sessions',
            '-S', '127.0.0.1:' . $server->port,
            '-t', dirname(__DIR__, 2),
        ];
        $log = ['file', $server->dir . '/server.log', 'a'];
        $env = array_merge(getenv(), ['AW_TEST_DIR' => $server->dir]);
        $server->process = proc_open($command, [0 => ['pipe', 'r'], 1 => $log, 2 => $log], $pipes, null, $env);

        for ($i = 0; $i < 100; $i++) {
            $socket = @stream_socket_client('tcp://127.0.0.1:' . $server->port, $errno, $errstr, 0.2);
            if ($socket) {
                fclose($socket);
                return $server;
            }
            usleep(50000);
        }
        $server->stop();
        throw new RuntimeException('php -S did not start on port ' . $server->port);
    }

    /** http://127.0.0.1:<port><path> */
    public function url(string $path): string
    {
        return 'http://127.0.0.1:' . $this->port . $path;
    }

    /** Path of $name in the directory the server's scripts see as AW_TEST_DIR. */
    public function file(string $name): string
    {
        return $this->dir . '/' . $name;
    }

    /**
     * Sends GET $path and returns the reply, without following redirects.
     *
     * @return array{status: int, headers: array<string, string>, body: string} header names in lower case
     */
    public function request(string $path): array
    {
        $socket = stream_socket_client('tcp://127.0.0.1:' . $this->port, $errno, $errstr, 5);
        stream_set_timeout($socket, 10);
        fwrite($socket, "GET $path HTTP/1.0\r\nHost: 127.0.0.1:{$this->port}\r\nConnection: close\r\n\r\n");
        $raw = stream_get_contents($socket);
        fclose($socket);

        [$head, $body] = array_pad(explode("\r\n\r\n", $raw, 2), 2, '');
        $lines = explode("\r\n", $head);
        preg_match('#^HTTP/\S+ (\d{3})#', array_shift($lines), $status);
        $headers = [];
        foreach ($lines as $line) {
            [$name, $value] = array_pad(explode(':', $line, 2), 2, '');
            $headers[strtolower(trim($name))] = trim($value);
        }

        return ['status' => (int) ($status[1] ?? 0), 'headers' => $headers, 'body' => $body];
    }

    public function stop(): void
    {
        if (is_resource($this->process)) {
            proc_terminate($this->process);
            proc_close($this->process);
        }
        foreach (array_merge(glob($this->dir . '/*') ?: [], glob($this->dir . '/sessions/*') ?: []) as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        @rmdir($this->dir . '/sessions');
        @rmdir($this->dir);
    }
}
