<?php

namespace SailCMS\Models;

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;
use SailCMS\ACL;
use SailCMS\Collection;
use SailCMS\Database\BaseModel;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\ACLException;
use SailCMS\Event;
use SailCMS\Security;
use SailCMS\Session;
use SailCMS\Types\Listing;
use SailCMS\Types\Pagination;
use SailCMS\Types\QueryOptions;
use SailCMS\Types\UserMeta;
use SailCMS\Types\Username;

class User extends BaseModel
{
    public const EVENT_DELETE = 'event_delete_user';
    public const EVENT_CREATE = 'event_create_user';
    public const EVENT_UPDATE = 'event_update_user';

    public static ?User $currentUser = null;

    private static Collection $permsCache;

    public Username $name;
    public Collection $roles;
    public string $email;
    public string $status;
    public string $password;
    public string $avatar;
    public UserMeta $meta;
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
                'temporary_token'
            ];
        }

        return ['_id', 'name', 'roles', 'email', 'status', 'avatar', 'meta', 'temporary_token'];
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
     * @throws ACLException
     *
     */
    public function getById(string $id): ?User
    {
        if ((isset(static::$currentUser) && (string)static::$currentUser->_id === $id) || ACL::hasPermission(static::$currentUser, ACL::read('user'))) {
            return $this->findById($id)->exec();
        }

        return null;
    }

    /**
     *
     * Get a user by his email
     *
     * @param  string  $email
     * @return User|null
     * @throws DatabaseException
     *
     */
    public function getByEmail(string $email): ?User
    {
        return $this->findOne(['email' => $email])->exec();
    }

    /**
     *
     * Create a regular user (usually user from the site) with no roles.
     *
     * @param  Username       $name
     * @param  string         $email
     * @param  string         $password
     * @param  string         $avatar
     * @param  UserMeta|null  $meta
     * @return string
     * @throws DatabaseException
     *
     */
    public function createRegularUser(Username $name, string $email, string $password, string $avatar = '', UserMeta|null $meta = null): string
    {
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
            'roles' => [],
            'avatar' => $avatar,
            'password' => Security::hashPassword($password),
            'meta' => $meta->simplify(),
            'temporary_token' => ''
        ]);
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
     * @param  UserMeta|null  $meta
     * @return string
     * @throws ACLException
     * @throws DatabaseException
     *
     */
    public function create(Username $name, string $email, string $password, Collection $roles, string $avatar = '', UserMeta|null $meta = null): string
    {
        if (ACL::hasPermission(static::$currentUser, ACL::write('user'))) {
            // Make sure full is assigned
            if ($name->full === '') {
                $name = new Username($name->first, $name->last, $name->first . ' ' . $name->last);
            }

            if ($meta === null) {
                $meta = new UserMeta((object)['flags' => ['use2fa' => false]]);
            }

            return $this->insert([
                'name' => $name,
                'email' => $email,
                'status' => true,
                'roles' => $roles,
                'avatar' => $avatar,
                'password' => Security::hashPassword($password),
                'meta' => $meta->simplify(),
                'temporary_token' => ''
            ]);
        }

        return '';
    }

    public function update(): bool
    {
        if ((string)static::$currentUser->_id === (string)$this->_id || ACL::hasPermission(static::$currentUser, ACL::write('user'))) {
            // TODO: update user
        }
    }

    /**
     *
     * Get a list of users
     *
     * @param  int     $page
     * @param  int     $limit
     * @param  string  $search
     * @param  string  $sort
     * @param  int     $direction
     * @return Listing
     * @throws ACLException
     * @throws DatabaseException
     *
     */
    public function getList(int $page = 0, int $limit = 25, string $search = '', string $sort = 'name.first', int $direction = BaseModel::SORT_ASC): Listing
    {
        if (ACL::hasPermission(static::$currentUser, ACL::read('user'))) {
            $offset = $page * $limit - $limit; // (ex: 1 * 25 - 25 = 0 offset)

            $options = QueryOptions::initWithSort([$sort => $direction]);
            $options->skip = $offset;
            $options->limit = ($limit > 100) ? 25 : $limit;

            $query = [];

            if (!empty($search)) {
                $query = [
                    '$or' => [
                        ['name.full' => new Regex($search, 'gi')],
                        ['email' => $search]
                    ]
                ];
            }

            // Pagination
            $total = $this->count($query);
            $pages = ceil($total / $limit);
            $current = $page;
            $pagination = new Pagination($current, $pages, $total);

            $list = $this->find($query, $options)->exec();

            return new Listing($pagination, new Collection($list));
        }

        return Listing::empty();
    }

    /**
     *
     * Delete the user from this instance
     *
     * @return bool
     * @throws DatabaseException
     * @throws ACLException
     *
     */
    public function remove(): bool
    {
        if (ACL::hasPermission(static::$currentUser, ACL::write('user'))) {
            $this->deleteById($this->_id);
            Event::dispatch(static::EVENT_DELETE, (string)$this->_id);
            return true;
        }

        return false;
    }

    /**
     *
     * Delete a user by his id
     *
     * @param  string|ObjectId  $id
     * @return bool
     * @throws DatabaseException
     * @throws ACLException
     *
     */
    public function removeById(string|ObjectId $id): bool
    {
        if (ACL::hasPermission(static::$currentUser, ACL::write('user'))) {
            if (is_string($id)) {
                $id = new ObjectId($id);
            }

            $this->deleteById($id);
            Event::dispatch(static::EVENT_DELETE, (string)$id);
            return true;
        }

        return false;
    }

    /**
     *
     * Delete a user by his email
     *
     * @param  string  $email
     * @return bool
     * @throws DatabaseException
     * @throws ACLException
     *
     */
    public function removeByEmail(string $email): bool
    {
        if (ACL::hasPermission(static::$currentUser, ACL::write('user'))) {
            $this->deleteOne(['email' => $email]);
            Event::dispatch(static::EVENT_DELETE, $email);
            return true;
        }

        return false;
    }

    // TODO: Update

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
            if ($user->meta->flags->use2fa) {
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
        echo "HERE";
        die();
        $user = $this->findOne(['temporary_token' => $token])->exec();

        if ($user) {
            $session = Session::manager();
            $session->setUserId((string)$user->_id);
            $session->set('user_id', (string)$user->_id);
            $session->get('set_token');

            static::$currentUser = $this->findById((string)$user->_id)->exec();

            // Get role
            $roleModel = new Role();

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

    /**
     *
     * Get users that are flagged with given flag
     *
     * @param  string  $flag
     * @return Collection
     * @throws DatabaseException
     * @throws ACLException
     *
     */
    public static function flagged(string $flag): Collection
    {
        if (ACL::hasPermission(static::$currentUser, ACL::read('user'))) {
            $instance = new static();
            return new Collection($instance->find(["meta.flags.{$flag}" => ['$exists' => true, '$eq' => true]])->exec());
        }

        return new Collection([]);
    }

    /**
     *
     * Get users who are not flagged with the given flag
     *
     * @param  string  $flag
     * @return Collection
     * @throws DatabaseException
     * @throws ACLException
     *
     */
    public static function notFlagged(string $flag): Collection
    {
        if (ACL::hasPermission(static::$currentUser, ACL::read('user'))) {
            $instance = new static();
            return new Collection($instance->find([
                '$or' => [
                    ["meta.flags.{$flag}" => ['$exists' => false]],
                    ["meta.flags.{$flag}" => false]
                ]
            ])->exec());
        }

        return new Collection([]);
    }
}