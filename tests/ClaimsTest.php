<?php

/**
 * ID token claim checks in _aw_validate_claims(): issuer, audience, expiry and issued-at with the 60-second
 * clock_skew, and the nonce. Times are relative to time(), at least 30 seconds from each skew edge.
 */
final class ClaimsTest extends ClientTestCase
{
    public function testValidClaimsPass(): void
    {
        $this->assertTrue(_aw_validate_claims($this->claims(), $this->config(), 'n1'));
    }

    public function testIssuerMustMatchExactly(): void
    {
        $this->assertFalse(_aw_validate_claims($this->claims(['iss' => 'https://app.agewallet.io/']), $this->config(), 'n1'));
        $this->assertFalse(_aw_validate_claims($this->claims(['iss' => 'https://other.example']), $this->config(), 'n1'));
        $missing = $this->claims();
        unset($missing['iss']);
        $this->assertFalse(_aw_validate_claims($missing, $this->config(), 'n1'));
    }

    public function testStringAudienceMustBeTheClientId(): void
    {
        $this->assertFalse(_aw_validate_claims($this->claims(['aud' => 'other-client']), $this->config(), 'n1'));
        $missing = $this->claims();
        unset($missing['aud']);
        $this->assertFalse(_aw_validate_claims($missing, $this->config(), 'n1'));
    }

    public function testArrayAudienceMustContainTheClientId(): void
    {
        $this->assertTrue(_aw_validate_claims($this->claims(['aud' => ['other-client', 'cid']]), $this->config(), 'n1'));
        $this->assertFalse(_aw_validate_claims($this->claims(['aud' => ['other-client']]), $this->config(), 'n1'));
    }

    public function testExpiryAllowsTheClockSkewOnly(): void
    {
        $this->assertTrue(_aw_validate_claims($this->claims(['exp' => time() - 30]), $this->config(), 'n1'));
        $this->assertFalse(_aw_validate_claims($this->claims(['exp' => time() - 90]), $this->config(), 'n1'));
    }

    public function testMissingExpiryFails(): void
    {
        $claims = $this->claims();
        unset($claims['exp']);
        $this->assertFalse(_aw_validate_claims($claims, $this->config(), 'n1'));
    }

    public function testIssuedAtInTheFutureAllowsTheClockSkewOnly(): void
    {
        $this->assertTrue(_aw_validate_claims($this->claims(['iat' => time() + 30]), $this->config(), 'n1'));
        $this->assertFalse(_aw_validate_claims($this->claims(['iat' => time() + 120]), $this->config(), 'n1'));
    }

    public function testNonceMustMatch(): void
    {
        $this->assertFalse(_aw_validate_claims($this->claims(['nonce' => 'n2']), $this->config(), 'n1'));
        $missing = $this->claims();
        unset($missing['nonce']);
        $this->assertFalse(_aw_validate_claims($missing, $this->config(), 'n1'));
    }

    public function testClockSkewComesFromTheConfig(): void
    {
        $config = $this->config(['clock_skew' => 300]);
        $this->assertTrue(_aw_validate_claims($this->claims(['exp' => time() - 200]), $config, 'n1'));
        $this->assertFalse(_aw_validate_claims($this->claims(['exp' => time() - 400]), $config, 'n1'));
    }
}
