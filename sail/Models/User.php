<?php

namespace SailCMS\Models;

use SailCMS\ACL;
use SailCMS\Collection;
use SailCMS\Database\BaseModel;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\ACLException;
use SailCMS\Security;
use SailCMS\Session;
use SailCMS\Types\UserMeta;
use SailCMS\Types\Username;

class User extends BaseModel
{
    public static ?User $currentUser = null;

    private static Collection $permsCache;

    public Username $name;
    public Collection $roles;
    public string $email;
    public string $status;
    public string $password;
    public string $avatar;
    public UserMeta $meta;
    public bool $use2fa;
    public string $temporary_token;

    public function fields(bool $fetchAllFields = false): array
    {
        if ($fetchAllFields) {
            return [
                '_id',
                'name',
                'roles',
                'email',
                'status',
                'avatatar',
                'meta',
                'password',
                'use2fa',
                'temporary_token'
            ];
        }

        return ['_id', 'name', 'roles', 'email', 'status', 'avatar', 'meta', 'use2fa', 'temporary_token'];
    }

    protected function processOnFetch(string $field, mixed $value): mixed
    {
        if ($field === 'name') {
            return new Username($value->first, $value->last, $value->full);
        }

        if ($field === 'meta') {
            return new UserMeta($value);
        }

        return $value;
    }

    /**
     *
     * Authenticate the user with the session
     *
     * @return void
     * @throws DatabaseException
     *
     */
    public static function authenticate(): void
    {
        // Check session for current $user id
        $session = Session::manager();

        if ($session->type() === 'stateless') {
            $uid = $session->getId(); // Get the user ID from the stateless session
        } else {
            $uid = $session->get('user_id');
        }

        if (!empty($uid)) {
            $instance = new static();
            static::$currentUser = $instance->findById($uid)->exec();
        }
    }

    /**
     *
     * Is the user authenticated?
     *
     * @return bool
     *
     */
    public static function isAuthenticated(): bool
    {
        return (isset(static::$currentUser));
    }

    /**
     *
     * Get user's permission
     *
     * @param  bool  $forceLoad
     * @return Collection
     * @throws DatabaseException
     *
     */
    public function permissions(bool $forceLoad = false): Collection
    {
        if (isset(static::$permsCache)) {
            return static::$permsCache;
        }

        $roleModel = new Role();
        $permissions = new Collection([]);

        foreach ($this->roles->unwrap() as $roleSlug) {
            $role = $roleModel->getByName($roleSlug);

            if ($role) {
                $permissions->push(...$role->permissions);
            }
        }

        static::$permsCache = $permissions;

        return $permissions;
    }

    /**
     *
     * Get a user by id
     *
     * @param  string  $id
     * @return User|null
     * @throws DatabaseException
     *
     */
    public function getById(string $id): ?User
    {
        return $this->findById($id)->exec();
    }

    /**
     *
     * Create a new user
     *
     * @param  Username       $name
     * @param  string         $email
     * @param  string         $password
     * @param  Collection     $roles
     * @param  string         $avatar
     * @param  UserMeta|null  $meta  $
     * @return string
     * @throws ACLException
     * @throws DatabaseException
     */
    public function create(Username $name, string $email, string $password, Collection $roles, string $avatar = '', UserMeta|null $meta = null): string
    {
        if (ACL::hasPermission(static::$currentUser, ACL::write('user'))) {
            // Check if the current user is allowed to create the request level of user

            // Make sure full is assigned
            if ($name->full === '') {
                $name = new Username($name->first, $name->last, $name->first . ' ' . $name->last);
            }

            if ($meta === null) {
                $meta = new UserMeta((object)[]);
            }

            return $this->insert([
                'name' => $name,
                'email' => $email,
                'status' => true,
                'roles' => $roles,
                'avatar' => $avatar,
                'password' => Security::hashPassword($password),
                'meta' => $meta->simplify(),
                'use2fa' => false,
                'temporary_token' => ''
            ]);
        }

        return '';
    }

    /**
     *
     * Perform pre-login verification
     *
     * Returns: 2fa, a secure temporary login key or error
     *
     * 2fa = Require 2FA to continue
     * key = use this key to log in without resending the user's email and password
     * error = user does not exist or password is wrong
     *
     * @param  string  $email
     * @param  string  $password
     * @return string
     * @throws DatabaseException
     *
     */
    public function verifyUserPass(string $email, string $password): string
    {
        $user = $this->findOne(['email' => $email])->exec(true);

        if ($user && Security::verifyPassword($password, $user->password)) {
            if ($user->use2fa) {
                return '2fa';
            }

            $key = Security::secureTemporaryKey();
            $this->updateOne(['_id' => $user->_id], ['$set' => ['temporary_token' => $key]]);
            return $key;
        }

        return 'error';
    }

    /**
     *
     * Authenticate a user by its temporary token
     *
     * @param  string  $token
     * @return User|null
     * @throws DatabaseException
     *
     */
    public function verifyTemporaryToken(string $token): ?User
    {
        $user = $this->findOne(['temporary_token' => $token])->exec();

        if ($user) {
            $session = Session::manager();
            $session->$session->get('set_token');

            static::$currentUser = $this->findById((string)$user->_id)->exec();

            // Get role
            $roleModel = new Role();

            static::$currentUser->permissions = new Collection([]);

            foreach (static::$currentUser->roles->unwrap() as $roleSlug) {
                $role = $roleModel->getByName($roleSlug);

                if ($role) {
                    static::$currentUser->permissions = $role->permissions;
                }
            }

            // Clear temporary token
            $this->updateOne(['_id' => $user->_id], ['$set' => ['temporary_token' => '']]);

            return static::$currentUser;
        }

        return null;
    }

    /**
     *
     * Log user in
     *
     * @param  string  $email
     * @param  string  $password
     * @return bool
     * @throws DatabaseException
     *
     */
    public function login(string $email, string $password): bool
    {
        $user = $this->findOne(['email' => $email])->exec(true);

        if ($user && Security::verifyPassword($password, $user->password)) {
            $session = Session::manager();
            $session->$session->get('set_token');

            $instance = new static();
            static::$currentUser = $instance->findById((string)$user->_id)->exec();

            // Get role
            $roleModel = new Role();

            static::$currentUser->permissions = new Collection([]);

            foreach (static::$currentUser->roles->unwrap() as $roleSlug) {
                $role = $roleModel->getByName($roleSlug);

                if ($role) {
                    static::$currentUser->permissions = $role->permissions;
                }
            }

            return true;
        }

        return false;
    }

    /**
     *
     * Log user out
     *
     * @return void
     *
     */
    public function logout(): void
    {
        $session = Session::manager();
        $session->clear();
        static::$currentUser = null;
    }

    /**
     *
     * Check if a user has the given flag in his metadata
     *
     * @param  string  $key
     * @return bool
     *
     */
    public function has(string $key): bool
    {
        // Check for existence and if it's true
        return (isset($this->meta->flags->{$key}) && $this->meta->flags->{$key});
    }

    /**
     *
     * Set a flag in the user's metadata
     *
     * @param  string  $key
     * @return void
     * @throws DatabaseException
     *
     */
    public function flag(string $key): void
    {
        $flags = $this->meta->flags;
        $flags->{$key} = true;

        $this->updateOne(['_id' => $this->_id], ['$set' => ['meta.flags' => $flags]]);
    }
}