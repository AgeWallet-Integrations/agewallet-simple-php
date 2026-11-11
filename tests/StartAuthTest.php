<?php

/**
 * agewallet_start_auth(), run by tests/fixtures/start-auth.php on BuiltInServer because it ends in header() and exit.
 * The script's reply carries the Location header and, as its body, the session it saved.
 */
final class StartAuthTest extends ClientTestCase
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
     * Starts the flow with the shipped config plus client_id "cid", redirect_uri https://shop.example/ and $overrides.
     *
     * @return array{status: int, location: string, params: array, session: ?array}
     */
    private function start(array $overrides = []): array
    {
        $config = array_merge(['client_id' => 'cid', 'redirect_uri' => 'https://shop.example/'], $overrides);
        file_put_contents(self::$server->file('config.json'), json_encode($config));

        $response = self::$server->request('/tests/fixtures/start-auth.php?config=config.json');
        $location = $response['headers']['location'] ?? '';
        parse_str((string) parse_url($location, PHP_URL_QUERY), $params);

        return [
            'status' => $response['status'],
            'location' => $location,
            'params' => $params,
            'session' => json_decode($response['body'], true),
        ];
    }

    public function testRedirectsToTheAuthorizeUrlWithTheOidcParameters(): void
    {
        $result = $this->start();

        $this->assertSame(302, $result['status']);
        $this->assertSame(0, strpos($result['location'], 'https://app.agewallet.io/user/authorize?'), $result['location']);
        $this->assertSame(
            ['response_type', 'client_id', 'redirect_uri', 'scope', 'state', 'nonce', 'code_challenge', 'code_challenge_method'],
            array_keys($result['params'])
        );
        $this->assertSame('code', $result['params']['response_type']);
        $this->assertSame('cid', $result['params']['client_id']);
        $this->assertSame('https://shop.example/', $result['params']['redirect_uri']);
        $this->assertSame('openid age', $result['params']['scope']);
        $this->assertSame('S256', $result['params']['code_challenge_method']);
    }

    public function testSavesTheStateNonceAndVerifierItSends(): void
    {
        $result = $this->start();
        $session = $result['session'];

        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $session['aw_state']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $session['aw_nonce']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{128}$/', $session['aw_verifier']);
        $this->assertSame($session['aw_state'], $result['params']['state']);
        $this->assertSame($session['aw_nonce'], $result['params']['nonce']);
        $this->assertSame(Keys::b64u(hash('sha256', $session['aw_verifier'], true)), $result['params']['code_challenge']);
    }

    public function testEachStartMakesNewValues(): void
    {
        $first = $this->start()['params'];
        $second = $this->start()['params'];

        foreach (['state', 'nonce', 'code_challenge'] as $name) {
            $this->assertNotSame($first[$name], $second[$name], $name);
        }
    }

    public function testUsesTheConfiguredAuthorizeUrlAndScopes(): void
    {
        $result = $this->start(['authorize_url' => 'https://dev3.agewallet.io/user/authorize', 'scopes' => 'openid age email']);

        $this->assertSame(0, strpos($result['location'], 'https://dev3.agewallet.io/user/authorize?'), $result['location']);
        $this->assertSame('openid age email', $result['params']['scope']);
    }

    public function testLeavesMetadataOutWhenNoneIsSet(): void
    {
        $this->assertArrayNotHasKey('metadata', $this->start()['params']);
    }

    public function testSendsMetadataUpTo4096Bytes(): void
    {
        $this->assertSame('order:42 & co', $this->start(['metadata' => 'order:42 & co'])['params']['metadata']);

        $atLimit = str_repeat('a', 4096);
        $this->assertSame($atLimit, $this->start(['metadata' => $atLimit])['params']['metadata']);
    }

    public function testMetadataOver4096BytesStopsWithAnError(): void
    {
        $result = $this->start(['metadata' => str_repeat('a', 4097)]);

        $this->assertSame(500, $result['status']);
        $this->assertSame('', $result['location']);
    }
}
