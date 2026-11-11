<?php

/**
 * RSA keys, JWKs and signed JWTs made at test time, and temp files for JWKS and token responses
 * (agewallet-client.php reads jwks_uri and token_url with file_get_contents, so a file path works in place of a URL).
 * Temp files are deleted when the test run ends.
 */
final class Keys
{
    /** @var string[] */
    private static $files = [];

    public static function b64u(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * @return array{0: mixed, 1: array, 2: string} private key, its public JWK with the given kid, its public PEM
     */
    public static function rsa(string $kid = 'k1', int $bits = 2048): array
    {
        $key = openssl_pkey_new(['private_key_bits' => $bits, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $details = openssl_pkey_get_details($key);
        $jwk = [
            'kty' => 'RSA',
            'kid' => $kid,
            'use' => 'sig',
            'alg' => 'RS256',
            'n' => self::b64u($details['rsa']['n']),
            'e' => self::b64u($details['rsa']['e']),
        ];

        return [$key, $jwk, $details['key']];
    }

    /** A compact JWT of $payload, signed with $privateKey using $algo, with $header as its header. */
    public static function sign(array $payload, $privateKey, array $header = ['alg' => 'RS256', 'typ' => 'JWT', 'kid' => 'k1'], int $algo = OPENSSL_ALGO_SHA256): string
    {
        $signed = self::b64u(json_encode($header)) . '.' . self::b64u(json_encode($payload));
        openssl_sign($signed, $signature, $privateKey, $algo);

        return $signed . '.' . self::b64u($signature);
    }

    /** Path of a new temp file holding $contents. */
    public static function file(string $contents): string
    {
        if (!self::$files) {
            register_shutdown_function(static function (): void {
                foreach (self::$files as $file) {
                    @unlink($file);
                }
            });
        }
        $file = tempnam(sys_get_temp_dir(), 'aw');
        file_put_contents($file, $contents);
        self::$files[] = $file;

        return $file;
    }
}
