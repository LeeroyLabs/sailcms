<?php

namespace SailCMS;

use Exception;
use League\Flysystem\FilesystemException;
use SailCMS\Http\Request;
use SailCMS\Models\CSRF;
use SodiumException;

class Security
{
    public static string $overrideKey = '';

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
        $path = 'local://storage/fs/vault';

        if (static::$overrideKey) {
            return;
        }

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
     * Encrypt a string safely
     *
     * @throws Exception
     * @throws FilesystemException
     *
     */
    public static function encrypt(string $data): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $key = (static::$overrideKey === '') ? Filesystem::manager()->read('local://storage/fs/vault/.security_key') : static::$overrideKey;
        $encrypted = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($data, '', $nonce, $key);

        $nonceHex = bin2hex($nonce);
        $final = bin2hex($encrypted);

        return $final . '.' . $nonceHex;
    }

    /**
     *
     * Decrypt a string safely
     *
     * @param  string  $encrypted
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
        $key = (static::$overrideKey === '') ? Filesystem::manager()->read('local://storage/fs/vault/.security_key') : static::$overrideKey;
        return sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($hash, '', $nonce, $key);
    }

    /**
     *
     * Hash a string with or without salting it
     *
     * @param  string  $data
     * @param  bool    $salted
     * @return string
     * @throws Exception
     *
     */
    public static function hash(string $data, bool $salted = true): string
    {
        if ($salted) {
            $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
            $nonceHex = bin2hex($nonce);
            $prefix = '$' . hash('sha256', $nonceHex);
            $hash = hash('sha256', $data);
            return $prefix . $hash;
        }

        return hash('sha256', $data);
    }

    /**
     *
     * A smart hash compare method. It detects whether you used salting during hashing.
     * Note: this only detects SailCMS hashing techniques, not anything else.
     *
     * @param  string  $hash
     * @param  string  $compare
     * @return bool
     *
     */
    public static function valueMatchHash(string $hash, string $compare): bool
    {
        if ($hash[0] === '$') {
            // Hash is salted
            $hashValue = substr($hash, 65); // 1 for the $ and 64 for the salt
            $hash = hash('sha256', $compare);

            return ($hash === $hashValue);
        }

        $hashed = hash('sha256', $compare);
        return ($hashed === $hash);
    }

    /**
     *
     * Securely hash a password
     *
     * @param  string  $password
     * @return string
     *
     */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT, ['cost' => 9]);
    }

    /**
     *
     * Verify if a password matches given hash
     *
     * @param  string  $password
     * @param  string  $hash
     * @return bool
     *
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     *
     * Generate a rather secure key for temporary usage
     *
     * @return string
     *
     */
    public static function secureTemporaryKey(): string
    {
        return hash('sha256', microtime() . uniqid(uniqid('', true), true));
    }

    /**
     *
     * Create a CSRF token for a form
     *
     * @return string
     * @throws Exception
     *
     */
    public static function csrf(): string
    {
        return (new CSRF())->create();
    }

    /**
     *
     * Check if received CSRF is valid and set the result in the ENV variable
     *
     * @return void
     * @throws Errors\DatabaseException
     *
     */
    /**
     *
     * Check if received CSRF is valid and set the result in the ENV variable
     *
     * @return void
     * @throws Errors\DatabaseException
     *
     */
    public static function verifyCSRF(): void
    {
        $use = setting('CSRF.use', true);
        $fieldName = setting('CSRF.fieldName', '_csrf_');

        $request = new Request();
        $csrf = $request->post($fieldName, '');

        if ($use && !empty($csrf)) {
            $_ENV['CSRF_VALID'] = (new CSRF())->validate($csrf);
            return;
        }

        // If we don't use it, flag it as always valid
        if (!$use) {
            $_ENV['CSRF_VALID'] = true;
            return;
        }

        // We tried everything, fail!
        $_ENV['CSRF_VALID'] = false;
    }

    /**
     *
     * Validate password against configured level of security
     *
     * @param  string  $password
     * @param  bool    $enforceAll
     * @return bool
     *
     */
    public static function validatePassword(string $password, bool $enforceAll = false): bool
    {
        if (!$enforceAll) {
            $min = setting('passwords.minLength', 8);
            $max = setting('passwords.maxLength', 64);
            $alphanum = setting('passwords.enforceAlphanum', true);
            $mixcase = setting('passwords.enforceUpperLower', true);
            $special = setting('passwords.enforceSpecialChars', false);
        } else {
            // Most secure required
            $min = 8;
            $max = 64;
            $alphanum = true;
            $mixcase = true;
            $special = true;
        }

        $uppercase = preg_match('@[A-Z]@', $password);
        $lowercase = preg_match('@[a-z]@', $password);
        $an = preg_match('@[a-zA-Z0-9]@', $password);
        $specialChars = preg_match('@[^\w]@', $password);

        // Must have not word characters
        if ($special && !$specialChars) {
            return false;
        }

        // If missing mix of alpha and numbers, FAIL!
        if ($alphanum && !$an) {
            return false;
        }

        // If upper or lower is missing, FAIL!
        if ($mixcase && (!$uppercase || !$lowercase)) {
            return false;
        }

        // Length check
        if (strlen($password) < $min || strlen($password) > $max) {
            return false;
        }

        // Survived!
        return true;
    }

    /**
     *
     * Generate a verification code safe enough for this task
     *
     * @return string
     *
     */
    public static function generateVerificationCode(): string
    {
        return hash('sha256', uniqid(microtime(), true));
    }

    /**
     *
     * Generate a hash that can be replicated using a piece of data (object or array)
     *
     * @param  array|object  $data
     * @return string
     * @throws \JsonException
     *
     */
    public static function generateHashUsingObject(array|object $data): string
    {
        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    }
}