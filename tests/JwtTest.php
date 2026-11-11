<?php

/**
 * ID token signature checks: _aw_verify_jwt(), _aw_jwk_to_pem() and _aw_base64url_decode().
 */
final class JwtTest extends ClientTestCase
{
    public function testJwkToPemMatchesOpenSslsPemForA2048BitKey(): void
    {
        $this->assertSame(self::$pem, str_replace("\r\n", "\n", _aw_jwk_to_pem(self::$jwk)));
        $this->assertNotFalse(openssl_pkey_get_public(_aw_jwk_to_pem(self::$jwk)));
    }

    public function testJwkToPemMatchesOpenSslsPemForA4096BitKey(): void
    {
        [, $jwk, $pem] = Keys::rsa('k4096', 4096);
        $this->assertSame($pem, str_replace("\r\n", "\n", _aw_jwk_to_pem($jwk)));
    }

    public function testBase64UrlDecodeRoundTripsEveryPaddingLength(): void
    {
        foreach (['', 'a', 'ab', 'abc', 'abcd', "\xff\xfe\xfd", "\xfb\xff"] as $data) {
            $this->assertSame($data, _aw_base64url_decode(Keys::b64u($data)));
        }
    }

    public function testValidRs256SignaturePasses(): void
    {
        $this->assertTrue(_aw_verify_jwt(Keys::sign($this->claims(), self::$key), self::$jwksFile));
    }

    public function testRs384AndRs512SignaturesPass(): void
    {
        $rs384 = Keys::sign($this->claims(), self::$key, ['alg' => 'RS384', 'kid' => 'k1'], OPENSSL_ALGO_SHA384);
        $rs512 = Keys::sign($this->claims(), self::$key, ['alg' => 'RS512', 'kid' => 'k1'], OPENSSL_ALGO_SHA512);
        $this->assertTrue(_aw_verify_jwt($rs384, self::$jwksFile));
        $this->assertTrue(_aw_verify_jwt($rs512, self::$jwksFile));
    }

    public function testTamperedPayloadFails(): void
    {
        [$header, , $signature] = explode('.', Keys::sign($this->claims(), self::$key));
        $payload = Keys::b64u(json_encode($this->claims(['sub' => 'someone-else'])));
        $this->assertFalse(_aw_verify_jwt("$header.$payload.$signature", self::$jwksFile));
    }

    public function testTokenSignedByAnotherKeyFails(): void
    {
        [$otherKey] = Keys::rsa('k1');
        $this->assertFalse(_aw_verify_jwt(Keys::sign($this->claims(), $otherKey), self::$jwksFile));
    }

    public function testAlgNoneWithAnEmptySignatureFails(): void
    {
        $token = Keys::b64u(json_encode(['alg' => 'none', 'kid' => 'k1'])) . '.' . Keys::b64u(json_encode($this->claims())) . '.';
        $this->assertFalse(_aw_verify_jwt($token, self::$jwksFile));
    }

    public function testRs256SignatureUnderAnRs512HeaderFails(): void
    {
        $token = Keys::sign($this->claims(), self::$key, ['alg' => 'RS512', 'kid' => 'k1'], OPENSSL_ALGO_SHA256);
        $this->assertFalse(_aw_verify_jwt($token, self::$jwksFile));
    }

    public function testKeyIsChosenByKid(): void
    {
        [$key2, $jwk2] = Keys::rsa('k2');
        $jwks = Keys::file(json_encode(['keys' => [self::$jwk, $jwk2]]));

        $this->assertTrue(_aw_verify_jwt(Keys::sign($this->claims(), $key2, ['alg' => 'RS256', 'kid' => 'k2']), $jwks));
        $this->assertFalse(_aw_verify_jwt(Keys::sign($this->claims(), $key2, ['alg' => 'RS256', 'kid' => 'k1']), $jwks));
    }

    public function testJwksWithNoRsaKeyFails(): void
    {
        $jwks = Keys::file(json_encode(['keys' => [['kty' => 'EC', 'kid' => 'ec1']]]));
        $this->assertFalse(_aw_verify_jwt(Keys::sign($this->claims(), self::$key), $jwks));
    }

    public function testUnreadableJwksFails(): void
    {
        $this->assertFalse(_aw_verify_jwt(Keys::sign($this->claims(), self::$key), sys_get_temp_dir() . '/aw-missing-jwks.json'));
    }
}
