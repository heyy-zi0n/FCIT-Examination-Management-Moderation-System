<?php
/**
 * Encryption Service
 * Handles cryptographic operations for Blind Lockdown using AES-256-GCM
 */

class EncryptionService {
    private static ?string $key = null;

    /**
     * Get or derive 256-bit encryption key (Fail-Closed)
     */
    private static function getKey(): string {
        if (self::$key === null) {
            global $appConfig;
            $masterKey = trim((string)($appConfig['encryption_key'] ?? $_ENV['ENCRYPTION_KEY'] ?? getenv('ENCRYPTION_KEY') ?: ''));

            if (strlen($masterKey) < 32) {
                throw new RuntimeException(
                    'CRITICAL SECURITY ERROR: Master ENCRYPTION_KEY is missing or shorter than 32 bytes. Cryptographic operations aborted.'
                );
            }

            self::$key = hash('sha256', $masterKey, true); // 32 bytes binary
        }
        return self::$key;
    }

    /**
     * Encrypt file content using AES-256-GCM
     * Returns array with [ciphertext, iv (base64), tag (base64), original_sha256]
     */
    public static function encryptFileContent(string $data): array {
        $cipher = ENCRYPTION_CIPHER;
        $key = self::getKey();
        $ivLength = openssl_cipher_iv_length($cipher);
        $iv = openssl_random_pseudo_bytes($ivLength);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $data,
            $cipher,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '', // AAD
            16  // Tag length
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Encryption failed: ' . openssl_error_string());
        }

        $sha256 = hash('sha256', $data);

        return [
            'ciphertext' => $ciphertext,
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'checksum_sha256' => $sha256
        ];
    }

    /**
     * Decrypt file content using AES-256-GCM and verify integrity tag
     */
    public static function decryptFileContent(string $ciphertext, string $ivBase64, string $tagBase64, string $expectedSha256): string {
        $cipher = ENCRYPTION_CIPHER;
        $key = self::getKey();
        $iv = base64_decode($ivBase64, true);
        $tag = base64_decode($tagBase64, true);

        if ($iv === false || $tag === false) {
            throw new RuntimeException('Invalid encryption IV or authentication tag encoding.');
        }

        $plaintext = openssl_decrypt(
            $ciphertext,
            $cipher,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plaintext === false) {
            throw new RuntimeException('Decryption authentication failed. Tampering detected or invalid encryption parameters.');
        }

        // Verify SHA-256 checksum
        $actualSha256 = hash('sha256', $plaintext);
        if (!hash_equals($expectedSha256, $actualSha256)) {
            throw new RuntimeException('Decrypted file integrity checksum mismatch. File may be corrupted.');
        }

        return $plaintext;
    }
}
