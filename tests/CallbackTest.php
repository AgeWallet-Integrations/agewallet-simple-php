<?php

/**
 * agewallet_process_callback(): the gate decision. token_url points at a temp file holding the token response,
 * so the exchange needs no server; the session holds state "s1", nonce "n1" and verifier "v1".
 */
final class CallbackTest extends ClientTestCase
{
    private function arrange(?array $tokens, ?string $rawReply = null): void
    {
        $reply = $rawReply ?? json_encode($tokens);
        $GLOBALS['agewallet_config'] = $this->config(['token_url' => Keys::file($reply)]);
        $_SESSION = ['aw_state' => 's1', 'aw_nonce' => 'n1', 'aw_verifier' => 'v1'];
        $_GET = ['code' => 'c1', 'state' => 's1'];
    }

    private function idToken(array $overrides = []): string
    {
        return Keys::sign($this->claims($overrides), self::$key);
    }

    public function testValidCallbackVerifiesAndClearsTheFlowState(): void
    {
        $this->arrange(['id_token' => $this->idToken(), 'access_token' => Keys::sign(['metadata' => 'order:42'], self::$key)]);

        $this->assertTrue(agewallet_process_callback());
        $this->assertTrue(agewallet_is_verified());
        $this->assertSame('u1', agewallet_get_claims()['sub']);
        $this->assertTrue(agewallet_get_claims()['age_verified']);
        $this->assertSame('order:42', agewallet_get_metadata());
        foreach (['aw_state', 'aw_nonce', 'aw_verifier'] as $key) {
            $this->assertArrayNotHasKey($key, $_SESSION);
        }
    }

    public function testNoAccessTokenLeavesMetadataEmpty(): void
    {
        $this->arrange(['id_token' => $this->idToken()]);

        $this->assertTrue(agewallet_process_callback());
        $this->assertNull(agewallet_get_metadata());
    }

    public function testAccessTokenWithoutMetadataLeavesMetadataEmpty(): void
    {
        $this->arrange(['id_token' => $this->idToken(), 'access_token' => Keys::sign(['sub' => 'u1'], self::$key)]);

        $this->assertTrue(agewallet_process_callback());
        $this->assertNull(agewallet_get_metadata());
    }

    public function testStateThatDoesNotMatchFailsAndClearsAnEarlierVerification(): void
    {
        $this->arrange(['id_token' => $this->idToken()]);
        $_SESSION['aw_verified'] = true;
        $_GET['state'] = 'other';

        $this->assertFalse(agewallet_process_callback());
        $this->assertFalse(agewallet_is_verified());
    }

    public function testMissingStateFails(): void
    {
        $this->arrange(['id_token' => $this->idToken()]);
        unset($_GET['state']);

        $this->assertFalse(agewallet_process_callback());
        $this->assertFalse(agewallet_is_verified());
    }

    public function testNoSavedStateFails(): void
    {
        $this->arrange(['id_token' => $this->idToken()]);
        $_SESSION = [];

        $this->assertFalse(agewallet_process_callback());
        $this->assertFalse(agewallet_is_verified());
    }

    public function testErrorInTheCallbackFailsAndClearsAnEarlierVerification(): void
    {
        $_SESSION = ['aw_verified' => true, 'aw_state' => 's1'];
        $_GET = ['error' => 'access_denied', 'state' => 's1'];

        $this->assertFalse(agewallet_process_callback());
        $this->assertFalse(agewallet_is_verified());
    }

    public function testReplyWithoutAnIdTokenFails(): void
    {
        $this->arrange(['access_token' => 'x']);

        $this->assertFalse(agewallet_process_callback());
        $this->assertFalse(agewallet_is_verified());
    }

    public function testEmptyOrNonJsonReplyFails(): void
    {
        foreach (['', 'not json'] as $reply) {
            $this->arrange(null, $reply);

            $this->assertFalse(agewallet_process_callback(), var_export($reply, true));
            $this->assertFalse(agewallet_is_verified());
        }
    }

    public function testIdTokenSignedByAnotherKeyFails(): void
    {
        [$otherKey] = Keys::rsa('k1');
        $this->arrange(['id_token' => Keys::sign($this->claims(), $otherKey)]);

        $this->assertFalse(agewallet_process_callback());
        $this->assertFalse(agewallet_is_verified());
    }

    public function testIdTokenWithAnotherNonceFails(): void
    {
        $this->arrange(['id_token' => $this->idToken(['nonce' => 'replayed'])]);

        $this->assertFalse(agewallet_process_callback());
        $this->assertFalse(agewallet_is_verified());
    }

    public function testExpiredIdTokenFails(): void
    {
        $this->arrange(['id_token' => $this->idToken(['exp' => time() - 120])]);

        $this->assertFalse(agewallet_process_callback());
        $this->assertFalse(agewallet_is_verified());
    }

    public function testIdTokenForAnotherClientFails(): void
    {
        $this->arrange(['id_token' => $this->idToken(['aud' => 'other-client'])]);

        $this->assertFalse(agewallet_process_callback());
        $this->assertFalse(agewallet_is_verified());
    }
}
