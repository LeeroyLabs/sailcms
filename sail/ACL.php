<?php

namespace SailCMS;

use SailCMS\ACL\System;
use SailCMS\Errors\ACLException;
use SailCMS\Models\User;

class ACL
{
    private static Collection $loadedACL;

    /**
     *
     * Initialize the ACLs
     *
     * @return void
     *
     */
    public static function init(): void
    {
        if (!isset(static::$loadedACL)) {
            static::$loadedACL = new Collection([]);

            // Load all system ACL
            static::$loadedACL->pushSpread(...System::getAll()->unwrap());
        }
    }

    /**
     *
     * Get a permission in the READ list
     *
     * @param  string  $name
     * @return Types\ACL
     * @throws ACLException
     *
     */
    public static function read(string $name): Types\ACL
    {
        // Search through the read permissions for given name
        $permissionValue = null;

        static::$loadedACL->each(function ($key, $value) use (&$permissionValue, $name)
        {
            if ($value->category === 'R' && $value->providedName === $name) {
                $permissionValue = $value;
            }
        });

        if ($permissionValue === null) {
            throw new ACLException("No READ permission found under '{$name}'", 0404);
        }

        return $permissionValue;
    }

    /**
     *
     * Get a permission in the WRITE OR READWRITE list
     *
     * @param  string  $name
     * @return Types\ACL
     * @throws ACLException
     *
     */
    public static function write(string $name): Types\ACL
    {
        // Search through the read permissions for given name
        $permissionValue = null;

        static::$loadedACL->each(function ($key, $value) use (&$permissionValue, $name)
        {
            if (($value->category === 'W' || $value->category === 'RW') && $value->providedName === $name) {
                $permissionValue = $value;
            }
        });

        if ($permissionValue === null) {
            throw new ACLException("No WRITE or READWRITE permission found under '{$name}'", 0404);
        }

        return $permissionValue;
    }

    /**
     *
     * Get a permission in the READWRITE list
     *
     * @param  string  $name
     * @return Types\ACL
     * @throws ACLException
     *
     */
    public static function readwrite(string $name): Types\ACL
    {
        // Search through the read permissions for given name
        $permissionValue = null;

        static::$loadedACL->each(function ($key, $value) use (&$permissionValue, $name)
        {
            if ($value->category === 'RW' && $value->providedName === $name) {
                $permissionValue = $value;
            }
        });

        if ($permissionValue === null) {
            throw new ACLException("No READWRITE permission found under '{$name}'", 0404);
        }

        return $permissionValue;
    }

    /**
     *
     * Initialize the custom ACLs
     *
     * @param  Collection  $acls
     * @return void
     *
     */
    public static function loadCustom(Collection $acls): void
    {
        static::$loadedACL->pushSpread(...$acls->unwrap());
    }

    public static function hasRole(): bool
    {
        // Get User
        // Get Role
        return true;
    }

    /**
     *
     * Check if user has one of the given permissions
     *
     * @param  string|User  $userId
     * @param  Types\ACL    ...$permissions
     * @return bool
     */
    public static function hasPermission(string|User $userId, Types\ACL ...$permissions): bool
    {
        // Get User
        // Get Role
        // Validate
        //print_r($permissions);

        return true;
    }

    /**
     *
     * Check if user has all the given permissions
     *
     * @param  string|User  $userId
     * @param  Types\ACL    ...$permissions
     * @return bool
     */
    public static function hasAllPermissions(string|User $userId, Types\ACL ...$permissions): bool
    {
        return true;
    }
}