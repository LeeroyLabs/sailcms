<?php

namespace SailCMS;

use JsonException;
use Predis\Client;

final class Cache
{
    private static bool $isConnected = false;
    private static Client $client;

    public const TTL_HOUR = 3600;
    public const TTL_DAY = 86_400;
    public const TTL_WEEK = 604_800;
    public const TTL_MONTH = 2_592_000;

    public static function init(bool $forceUse = false): void
    {
        if (self::$isConnected) {
            return;
        }

        if (setting('cache.use', 'false') === 'true' || $forceUse) {
            $host = setting('cache.host', 'tcp://localhost');

            $opts = [
                'host' => setting('cache.host'),
                'port' => setting('cache.port', 6379),
                'database' => setting('cache.database', 10),
            ];

            // Auth
            if (setting('cache.user', '') !== '') {
                // 6.0+
                $opts['user'] = setting('cache.user');
                $opts['password'] = setting('cache.password');
            } elseif (setting('cache.password', '') !== '') {
                // up to 5.0.14
                $opts['password'] = setting('cache.password');
            }

            if (str_contains($host, 'tls://')) {
                $opts['cafile'] = setting('cache.ssl.cafile', '');
                $opts['verify_peer'] = setting('cache.ssl.verify', true);
            }

            self::$client = new Client($opts);
            self::$isConnected = true;
        }
    }

    /**
     *
     * Set a key's value
     *
     * @param string $key
     * @param mixed $value
     * @param int $ttl
     * @return void
     * @throws JsonException
     *
     */
    public static function set(string $key, mixed $value, int $ttl = Cache::TTL_WEEK): void
    {
        if (!self::$isConnected) {
            return;
        }

        if (!is_scalar($value)) {
            $isArray = is_array($value);
            $encType = ($isArray) ? 'array' : 'object';

            $value = 'json:' . $encType . ':=>:' . json_encode($value, JSON_THROW_ON_ERROR);
        }

        self::$client->set($key, $value, 'EX', $ttl);
    }

    /**
     *
     * Get a key's value
     *
     * @param string $key
     * @return mixed
     * @throws JsonException
     *
     */
    public static function get(string $key): mixed
    {
        if (!self::$isConnected) {
            return null;
        }

        $value = self::$client->get($key);

        if ($value && str_starts_with($value, 'json:')) {
            [$enc, $json] = explode(':=>:', $value);
            [$marker, $type] = explode(':', $enc);

            if ($type === 'array') {
                return json_decode($json, false, 512, JSON_THROW_ON_ERROR);
            }

            return json_decode($json, false, 512, JSON_THROW_ON_ERROR);
        }

        return $value;
    }

    /**
     *
     * Delete one or many keys
     *
     * @param array|Collection|string $key
     * @return void
     *
     */
    public static function remove(array|Collection|string $key): void
    {
        if (!self::$isConnected) {
            return;
        }

        if (is_string($key)) {
            self::$client->del($key);
            return;
        }

        if (is_array($key)) {
            self::$client->del($key);
            return;
        }

        self::$client->del($key->unwrap());
    }

    /**
     *
     * Remove all from db or globally (flushall)
     *
     * @param bool $global
     * @return void
     *
     */
    public static function removeAll(bool $global = false): void
    {
        if (!self::$isConnected) {
            return;
        }

        if ($global) {
            self::$client->flushall();
            return;
        }

        self::$client->flushdb();
    }

    /**
     *
     * Remove keys that match prefix
     *
     * @param string $prefix
     * @return bool
     *
     */
    public static function removeUsingPrefix(string $prefix): bool
    {
        if (!self::$isConnected) {
            return false;
        }

        $keys = self::$client->keys("{$prefix}:*");
        
        if (count($keys) === 0) {
            return false;
        }

        self::$client->del($keys);
        return true;
    }
}