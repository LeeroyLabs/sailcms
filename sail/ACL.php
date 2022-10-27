<?php

namespace SailCMS;

use SailCMS\ACL\System;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
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
     * @throws ACLException
     *
     */
    public static function loadCustom(Collection $acls): void
    {
        foreach ($acls->unwrap() as $acl) {
            if (!is_object($acl) || get_class($acl) !== Types\ACL::class) {
                throw new ACLException('Trying to load an ACL that is not of type \SailCMS\Types\ACL.');
            }

            if (in_array($acl->providedName, System::RESERVED)) {
                throw new ACLException("Cannot use reserved namespace '{$acl->providedName}'. Please choose something else.");
            }

            static::$loadedACL->push($acl);
        }
    }

    /**
     *
     * Check if current user or given user has required role
     *
     * @param  string|User|null  $user
     * @param  string            $role
     * @return bool
     * @throws DatabaseException
     * @throws ACLException
     *
     */
    public static function hasRole(string|User|null $user, string $role): bool
    {
        if ($user === null) {
            return false;
        }

        $role = Text::kebabCase(Text::deburr($role));

        if (is_string($user)) {
            $userModel = new User();
            $user = $userModel->getById($user);
        }

        return $user->roles->contains($role);
    }

    /**
     *
     * Check if user has one of the given permissions
     *
     * @param  string|User|null  $user
     * @param  Types\ACL         ...$permissions
     * @return bool
     * @throws DatabaseException
     * @throws ACLException
     *
     */
    public static function hasPermission(string|User|null $user, Types\ACL ...$permissions): bool
    {
        // CLI user is allowed RW on everything
        if (Sail::isCLI()) {
            return true;
        }

        if ($user === null) {
            return false;
        }

        if (is_string($user)) {
            $userModel = new User();
            $user = $userModel->getById($user);
        }

        if ($user) {
            $perms = [];

            foreach ($permissions as $permission) {
                $perms[] = $permission->value;
            }

            return ($user->permissions()->intersect($perms)->length > 0);
        }

        return false;
    }

    /**
     *
     * Check if user has all the given permissions
     *
     * @param  string|User|null  $user
     * @param  Types\ACL         ...$permissions
     * @return bool
     * @throws DatabaseException
     * @throws ACLException
     *
     */
    public static function hasAllPermissions(string|User|null $user, Types\ACL ...$permissions): bool
    {
        if ($user === null) {
            return false;
        }

        if (is_string($user)) {
            $userModel = new User();
            $user = $userModel->getById($user);
        }

        if ($user) {
            $perms = [];

            foreach ($permissions as $permission) {
                $perms[] = $permission->value;
            }

            return ($user->permissions()->intersect($permissions)->length === count($perms));
        }

        return true;
    }

    /**
     *
     * List of loaded permissions
     *
     * @return Collection
     *
     */
    public static function getList(): Collection
    {
        $list = new Collection([]);

        static::$loadedACL->each(function ($key, $value) use (&$list)
        {
            $list->push((object)[
                'group' => $value->providedName,
                'type' => $value->category,
                'value' => $value->value
            ]);
        });

        return $list;
    }

    /**
     *
     * Get the amount of loaded ACL
     *
     * @return int
     *
     */
    public static function count(): int
    {
        return static::$loadedACL->length;
    }
}