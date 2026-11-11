<?php

/**
 * _aw_exchange_code(): the POST to token_url, here tests/fixtures/token-endpoint.php on BuiltInServer, which records
 * the request and replies with a file's contents.
 */
final class TokenExchangeTest extends ClientTestCase
{
    /** @var BuiltInServer */
    private static $server;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$server = BuiltInServer::start();
    }

    public static function tearDownAfterClass(): void
    {
        self::$server->stop();
    }

    /**
     * Exchanges $code and $verifier with the endpoint replying $reply.
     *
     * @return array{0: ?array, 1: array} the function's result, and the recorded request (method, content_type, form)
     */
    private function exchange(string $reply, array $overrides = [], string $code = 'c1', string $verifier = 'v1'): array
    {
        file_put_contents(self::$server->file('reply.txt'), $reply);
        @unlink(self::$server->file('request.json'));
        $config = $this->config(array_merge([
            'token_url' => self::$server->url('/tests/fixtures/token-endpoint.php?reply=reply.txt&record=request.json'),
        ], $overrides));

        $result = _aw_exchange_code($code, $verifier, $config);

        $request = json_decode((string) file_get_contents(self::$server->file('request.json')), true);
        parse_str($request['body'], $form);
        $request['form'] = $form;

        return [$result, $request];
    }

    public function testPostsTheFormFieldsAndReturnsTheReply(): void
    {
        [$result, $request] = $this->exchange('{"id_token":"t1","access_token":"a1"}');

        $this->assertSame(['id_token' => 't1', 'access_token' => 'a1'], $result);
        $this->assertSame('POST', $request['method']);
        $this->assertSame('application/x-www-form-urlencoded', $request['content_type']);
        $this->assertSame([
            'grant_type' => 'authorization_code',
            'code' => 'c1',
            'redirect_uri' => 'https://shop.example/',
            'client_id' => 'cid',
            'code_verifier' => 'v1',
        ], $request['form']);
    }

    public function testSendsTheClientSecretOnlyWhenOneIsSet(): void
    {
        [, $without] = $this->exchange('{}');
        [, $with] = $this->exchange('{}', ['client_secret' => 's3cret']);

        $this->assertArrayNotHasKey('client_secret', $without['form']);
        $this->assertSame('s3cret', $with['form']['client_secret']);
    }

    public function testValuesWithReservedCharactersArriveWhole(): void
    {
        [, $request] = $this->exchange('{}', [], 'a&b=c+d e', 'v/1+2=3');

        $this->assertSame('a&b=c+d e', $request['form']['code']);
        $this->assertSame('v/1+2=3', $request['form']['code_verifier']);
    }

    public function testEmptyOrNonJsonReplyReturnsNull(): void
    {
        [$empty] = $this->exchange('');
        [$notJson] = $this->exchange('<html>error</html>');

        $this->assertNull($empty);
        $this->assertNull($notJson);
    }

    public function testUnreachableTokenUrlReturnsNull(): void
    {
        $probe = stream_socket_server('tcp://127.0.0.1:0');
        $closedPort = substr(stream_socket_get_name($probe, false), strrpos(stream_socket_get_name($probe, false), ':') + 1);
        fclose($probe);

        $this->assertNull(_aw_exchange_code('c1', 'v1', $this->config(['token_url' => "http://127.0.0.1:$closedPort/token"])));
    }
}
