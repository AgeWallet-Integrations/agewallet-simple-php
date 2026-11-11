<?php

use PHPUnit\Framework\TestCase;

/**
 * Base class for tests that call agewallet-client.php in this process. Each test class gets a fresh RSA key (kid "k1")
 * and a JWKS file holding it; each test starts with the shipped config overridden by config(), and empty $_SESSION
 * and $_GET.
 */
abstract class ClientTestCase extends TestCase
{
    /** @var mixed private key */
    protected static $key;
    /** @var array */
    protected static $jwk;
    /** @var string */
    protected static $pem;
    /** @var string */
    protected static $jwksFile;

    public static function setUpBeforeClass(): void
    {
        [self::$key, self::$jwk, self::$pem] = Keys::rsa('k1');
        self::$jwksFile = Keys::file(json_encode(['keys' => [self::$jwk]]));
    }

    protected function setUp(): void
    {
        $GLOBALS['agewallet_config'] = $this->config();
        $_SESSION = [];
        $_GET = [];
    }

    /** The shipped config with client_id "cid", issuer https://app.agewallet.io, the test JWKS, and $overrides. */
    protected function config(array $overrides = []): array
    {
        return array_merge($GLOBALS['agewallet_shipped_config'], [
            'client_id' => 'cid',
            'redirect_uri' => 'https://shop.example/',
            'issuer' => 'https://app.agewallet.io',
            'jwks_uri' => self::$jwksFile,
        ], $overrides);
    }

    /** ID token claims that pass _aw_validate_claims() for config() and nonce "n1", with $overrides. */
    protected function claims(array $overrides = []): array
    {
        return array_merge([
            'iss' => 'https://app.agewallet.io',
            'aud' => 'cid',
            'exp' => time() + 300,
            'iat' => time(),
            'nonce' => 'n1',
            'sub' => 'u1',
            'age_verified' => true,
        ], $overrides);
    }
}
