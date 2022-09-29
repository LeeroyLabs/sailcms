<?php

namespace SailCMS;

use Exception;
use League\Flysystem\FilesystemException;
use SodiumException;

class Security
{
    private static array $settings = [];

    /**
     *
     * Initialize security, generate an encryption key if required
     *
     * @return void
     *
     */
    public static function init(): void
    {
        $manager = Filesystem::manager();
        $path = 'local://vault';

        try {
            if ($manager->directoryExists($path) && !$manager->fileExists($path . '/.security_key')) {
                $key = sodium_crypto_aead_xchacha20poly1305_ietf_keygen();
                $manager->write($path . '/.security_key', $key, ['visibility' => 'private']);
            }
        } catch (FilesystemException $e) {
            throw new \RuntimeException('Could not create a secure encryption key. Please STOP! You should fix this now. Reason: ' . $e->getMessage());
        }
    }

    /**
     *
     * Load security settings
     *
     * @param array $settings
     * @return void
     *
     */
    public static function loadSettings(array $settings): void
    {
        static::$settings = $settings;
    }

    /**
     *
     * Encrypt a string safely
     *
     * @throws Exception
     * @throws FilesystemException
     *
     */
    public static function encrypt(string $data): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $key = Filesystem::manager()->read('local://vault/.security_key');
        $encrypted = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($data, '', $nonce, $key);

        $nonceHex = bin2hex($nonce);
        $final = bin2hex($encrypted);

        return $final . '.' . $nonceHex;
    }

    /**
     *
     * Decrypt a string safely
     *
     * @param string $encrypted
     * @return string
     * @throws FilesystemException
     * @throws SodiumException
     *
     */
    public static function decrypt(string $encrypted): string
    {
        @[$hashHex, $nonceHex] = explode('.', $encrypted);

        if (empty($nonceHex)) {
            return '';
        }

        $nonce = hex2bin($nonceHex);
        $hash = hex2bin($hashHex);
        $key = Filesystem::manager()->read('local://vault/.security_key');
        return sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($hash, '', $nonce, $key);
    }
}