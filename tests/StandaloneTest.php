<?php

use PHPUnit\Framework\TestCase;

/**
 * agewallet-client.php used on its own, as /agewallet-client.php?action=... on BuiltInServer, and loaded in include
 * mode by a separate PHP process.
 */
final class StandaloneTest extends TestCase
{
    /** @var BuiltInServer */
    private static $server;

    public static function setUpBeforeClass(): void
    {
        self::$server = BuiltInServer::start();
    }

    public static function tearDownAfterClass(): void
    {
        self::$server->stop();
    }

    public function testCheckAnswers0WithoutAVerification(): void
    {
        $this->assertSame('0', self::$server->request('/agewallet-client.php?action=check')['body']);
        $this->assertSame('0', self::$server->request('/agewallet-client.php')['body']);
    }

    public function testResetAnswers0(): void
    {
        $this->assertSame('0', self::$server->request('/agewallet-client.php?action=reset')['body']);
    }

    public function testUnknownActionAnswers0(): void
    {
        $this->assertSame('0', self::$server->request('/agewallet-client.php?action=unknown')['body']);
    }

    public function testCallbackWithoutStateAnswers0(): void
    {
        $this->assertSame('0', self::$server->request('/agewallet-client.php?action=callback&code=c1')['body']);
    }

    public function testStartRedirectsToTheAuthorizeUrl(): void
    {
        $response = self::$server->request('/agewallet-client.php?action=start');
        parse_str((string) parse_url($response['headers']['location'] ?? '', PHP_URL_QUERY), $params);

        $this->assertSame(302, $response['status']);
        $this->assertSame(0, strpos($response['headers']['location'] ?? '', 'https://app.agewallet.io/user/authorize?'));
        $this->assertSame('YOUR_CLIENT_ID', $params['client_id']);
        $this->assertArrayNotHasKey('metadata', $params);
    }

    public function testStartPassesTheMetadataParameterOn(): void
    {
        $response = self::$server->request('/agewallet-client.php?action=start&metadata=' . rawurlencode('order:42'));
        parse_str((string) parse_url($response['headers']['location'] ?? '', PHP_URL_QUERY), $params);

        $this->assertSame('order:42', $params['metadata']);
    }

    public function testIncludeModePrintsNothingAndRaisesNoErrors(): void
    {
        $code = "define('AGEWALLET_INCLUDE', true); require " . var_export(dirname(__DIR__) . '/agewallet-client.php', true) . ';';
        exec(escapeshellarg(PHP_BINARY) . ' -d error_reporting=-1 -d display_errors=1 -r ' . escapeshellarg($code) . ' 2>&1', $output, $status);

        $this->assertSame([], $output);
        $this->assertSame(0, $status);
    }
}
