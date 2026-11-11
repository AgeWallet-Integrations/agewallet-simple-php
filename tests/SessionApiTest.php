<?php

/**
 * agewallet_is_verified(), agewallet_get_claims(), agewallet_get_metadata() and agewallet_reset().
 */
final class SessionApiTest extends ClientTestCase
{
    public function testVerifiedOnlyForBooleanTrue(): void
    {
        foreach ([1, '1', 'yes', 'true'] as $value) {
            $_SESSION = ['aw_verified' => $value];
            $this->assertFalse(agewallet_is_verified(), var_export($value, true));
        }
        $_SESSION = ['aw_verified' => true];
        $this->assertTrue(agewallet_is_verified());
    }

    public function testNothingIsSetBeforeAVerification(): void
    {
        $this->assertFalse(agewallet_is_verified());
        $this->assertNull(agewallet_get_claims());
        $this->assertNull(agewallet_get_metadata());
    }

    public function testResetClearsTheVerification(): void
    {
        $_SESSION = ['aw_verified' => true, 'aw_claims' => ['sub' => 'u1'], 'aw_metadata' => 'm1'];

        agewallet_reset();

        $this->assertFalse(agewallet_is_verified());
        $this->assertNull(agewallet_get_claims());
        $this->assertNull(agewallet_get_metadata());
    }
}
