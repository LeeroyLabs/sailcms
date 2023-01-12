<?php

namespace SailCMS\Models;

use Exception;
use http\Exception\RuntimeException;
use SailCMS\Database\Model;
use SailCMS\Errors\DatabaseException;
use SailCMS\Http\Request;
use SailCMS\Security;
use SailCMS\Types\QueryOptions;

/**
 *
 * @property string $ip
 * @property string $token
 * @property int    $expire_at
 *
 */
class CSRF extends Model
{
    protected string $collection = 'csrf';

    /**
     *
     * Create a CSRF token and save it to database (stateless)
     *
     * @param  int  $overrideExpiration
     * @return string
     * @throws DatabaseException
     * @throws Exception
     *
     */
    public function create(int $overrideExpiration = -1): string
    {
        $token = Security::hash(microtime() . uniqid('', true), true);
        $ip = (new Request())->ipAddress();
        $expiration = setting('CSRF.expiration', 120);

        // 60sec minimum
        if ($overrideExpiration >= 60) {
            $expiration = $overrideExpiration;
        } elseif ($overrideExpiration >= 0) {
            throw new RuntimeException('Expiration of CSRF token cannot be lower than 60 seconds.', 0400);
        }

        // Delete all other for the ip
        $this->deleteMany(['ip' => $ip]);

        $this->insert([
            'ip' => $ip,
            'token' => $token,
            'expire_at' => time() + $expiration
        ]);

        return $token;
    }

    /**
     *
     * Validate a CSRF token from the database for the given ip
     *
     * @param  string  $receivedToken
     * @return bool
     * @throws DatabaseException
     *
     */
    public function validate(string $receivedToken): bool
    {
        $ip = (new Request())->ipAddress();
        $token = $this->findOne(['ip' => $ip], QueryOptions::initWithSort(['expire_at' => -1]))->exec();
        $leeway = setting('CSRF.leeway', 5);
        $now = time() + $leeway;

        if ($token !== null && $receivedToken === $token->token && $token->expire_at >= $now) {
            // Delete all other for the ip
            $this->deleteMany(['ip' => $ip]);
            return true;
        }

        return false;
    }
}