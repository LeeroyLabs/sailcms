<?php

namespace SailCMS\Models;

use Exception;
use http\Exception\RuntimeException;
use SailCMS\Database\BaseModel;
use SailCMS\Errors\DatabaseException;
use SailCMS\Http\Request;
use SailCMS\Security;
use SailCMS\Types\QueryOptions;

class CSRF extends BaseModel
{
    public string $ip;
    public string $token;
    public int $expire_at;

    public function __construct()
    {
        parent::__construct('csrf');
    }

    public function fields(bool $fetchAllFields = false): array
    {
        return ['ip', 'token', 'expire_at'];
    }

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
        $expiration = $_ENV['SETTINGS']->get('CSRF.expiration') ?? 120;

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
        $leeway = $_ENV['SETTINGS']->get('CSRF.leeway') ?? 5;
        $now = time() + $leeway;

        if ($token !== null && $receivedToken === $token->token && $token->expire_at >= $now) {
            // Delete all other for the ip
            $this->deleteMany(['ip' => $ip]);
            return true;
        }

        return false;
    }
}