<?php

namespace SailCMS\Security;

use RobThree\Auth\Providers\Qr\BaconQrCodeProvider;
use RobThree\Auth\Providers\Rng\OpenSSLRNGProvider;
use RobThree\Auth\Providers\Time\HttpTimeProvider;
use RobThree\Auth\TwoFactorAuth;
use RobThree\Auth\TwoFactorAuthException;
use SailCMS\Collection;
use SailCMS\Models\MFA;
use SailCMS\Models\User;

final class TwoFactorAuthentication
{
    private static TwoFactorAuth $auth;

    /**
     *
     * Set up the 2fa generator. Set the requested algorithm for creation.
     *
     * @throws TwoFactorAuthException
     *
     */
    public function __construct()
    {
        if (empty(self::$auth)) {
            self::$auth = new TwoFactorAuth(
                setting('tfa.issuer', 'sailcms'),
                setting('tfa.length', 6),
                setting('tfa.expire', 30),
                'sha1',
                new BaconQrCodeProvider(4, '#ffffff', '#000000', setting('tfa.format', 'svg')),
                new OpenSSLRNGProvider(),
                new HttpTimeProvider()
            );
        }
    }

    /**
     *
     * Create a signup code for 2FA Auth App
     *
     * @return string
     * @throws TwoFactorAuthException
     *
     */
    public function signup(): string
    {
        return self::$auth->createSecret();
    }

    /**
     *
     * Get the code from the given secret
     *
     * @param  string  $secret
     * @return string
     *
     */
    public function getCode(string $secret): string
    {
        return self::$auth->getCode($secret);
    }

    /**
     *
     * get a QR Code in Base64 form to display to your user
     *
     * @param  string  $secret
     * @return string
     * @throws TwoFactorAuthException
     *
     */
    public function getQRCode(string $secret): string
    {
        return self::$auth->getQRCodeImageAsDataUri('marc_leeroy.ca', $secret);
    }

    /**
     *
     * Check if a code is valid against a specific secret
     *
     * @param  string  $secret
     * @param  string  $code
     * @return bool
     *
     */
    public function validate(string $secret, string $code): bool
    {
        return self::$auth->verifyCode($secret, $code, 2);
    }

    /**
     *
     * Try to rescue an account with the given codes
     *
     * @param  Collection  $codes
     * @return ?User
     *
     */
    public function rescueAccount(Collection $codes): ?User
    {
        return (new MFA())->rescueAccount($codes);
    }
}