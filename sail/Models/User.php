<?php

namespace SailCMS\Models;

use SailCMS\ACL;
use SailCMS\Collection;
use SailCMS\Database\BaseModel;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\ACLException;
use SailCMS\Security;
use SailCMS\Session;
use SailCMS\Types\Username;

class User extends BaseModel
{
    public static ?User $currentUser = null;

    public Username $name;
    public Collection $roles;
    public string $email;
    public string $status;
    public string $password;
    public string $avatar;
    public Collection $permissions;
    public Collection $meta;

    public function fields(bool $fetchAllFields = false): array
    {
        if ($fetchAllFields) {
            return ['_id', 'name', 'roles', 'email', 'status', 'avatatar', 'meta', 'password'];
        }

        return ['_id', 'name', 'roles', 'email', 'status', 'avatar', 'meta'];
    }

    protected function processOnFetch(string $field, mixed $value): mixed
    {
        if ($field === 'name') {
            return new Username($value->first, $value->last, $value->full);
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

            // Get role
            $roleModel = new Role();

            static::$currentUser->permissions = new Collection([]);

            foreach (static::$currentUser->roles->unwrap() as $roleSlug) {
                $role = $roleModel->getByName($roleSlug);

                if ($role) {
                    static::$currentUser->permissions = $role->permissions;
                }
            }
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
     * Get a user by id
     *
     * @param  string  $id
     * @return User|null
     * @throws DatabaseException
     *
     */
    public function getById(string $id): ?User
    {
        $user = $this->findById($id)->exec();

        if ($user) {
            // Get role
            $roleModel = new Role();

            $user->permissions = new Collection([]);

            foreach ($user->roles as $roleSlug) {
                $role = $roleModel->getByName($roleSlug);

                if ($role) {
                    $user->permissions->pushSpread($role->permissions);
                }
            }
        }

        return $user;
    }

    /**
     *
     * Create a new user
     *
     * @param  Username    $name
     * @param  string      $email
     * @param  string      $password
     * @param  Collection  $roles
     * @param  string      $avatar
     * @param  array       $meta
     * @return string
     * @throws ACLException
     * @throws DatabaseException
     *
     */
    public function create(Username $name, string $email, string $password, Collection $roles, string $avatar = '', array $meta = []): string
    {
        if (ACL::hasPermission(User::$currentUser, ACL::write('user'))) {
            // Check if the current user is allowed to create the request level of user

            // Make sure full is assigned
            if ($name->full === '') {
                $name = new Username($name->first, $name->last, $name->first . ' ' . $name->last);
            }

            return $this->insert([
                'name' => $name,
                'email' => $email,
                'status' => true,
                'roles' => $roles,
                'avatar' => $avatar,
                'password' => Security::hashPassword($password),
                'meta' => $meta
            ]);
        }

        return '';
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
}