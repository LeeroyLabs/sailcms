<?php

namespace SailCMS\Models;

use Exception;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;
use SailCMS\ACL;
use SailCMS\Collection;
use SailCMS\Database\BaseModel;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\FileException;
use SailCMS\Event;
use SailCMS\Mail;
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
    public string $auth_token;
    public string $locale;

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
                'temporary_token',
                'locale'
            ];
        }

        return ['_id', 'name', 'roles', 'email', 'status', 'avatar', 'meta', 'temporary_token', 'locale'];
    }

    public static function initForTest()
    {
        static::$currentUser = new User();
        static::$currentUser->_id = new ObjectId();
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
     * @param  string         $locale
     * @param  string         $avatar
     * @param  UserMeta|null  $meta
     * @return string
     * @throws DatabaseException
     * @throws FileException
     *
     */
    public function createRegularUser(Username $name, string $email, string $password, string $locale = 'en', string $avatar = '', ?UserMeta $meta = null): string
    {
        // Make sure full is assigned
        if ($name->full === '') {
            $name = new Username($name->first, $name->last, $name->first . ' ' . $name->last);
        }

        if ($meta === null) {
            $meta = new UserMeta((object)[]);
        }

        // Validate email properly
        $this->validateEmail($email, '', true);

        // Validate password
        $valid = Security::validatePassword($password);

        if (!$valid) {
            throw new DatabaseException('Password does not pass minimum security level', 0403);
        }

        $id = $this->insert([
            'name' => $name,
            'email' => $email,
            'status' => true,
            'roles' => [],
            'avatar' => $avatar,
            'password' => Security::hashPassword($password),
            'meta' => $meta->simplify(),
            'temporary_token' => '',
            'locale' => $locale
        ]);

        if (!empty($id) && $_ENV['SETTINGS']->get('emails.sendNewAccount', false)) {
            // Send a nice email to greet
            try {
                $mail = new Mail();
                $mail->to($email)->useEmail('new_account', $locale)->send();
                return $id;
            } catch (Exception $e) {
                return $id;
            }
        }

        return $id;
    }

    /**
     *
     * Create a new user
     *
     * @param  Username       $name
     * @param  string         $email
     * @param  string         $password
     * @param  Collection     $roles
     * @param  string         $locale
     * @param  string         $avatar
     * @param  UserMeta|null  $meta
     * @return string
     * @throws ACLException
     * @throws DatabaseException
     *
     */
    public function create(Username $name, string $email, string $password, Collection $roles, string $locale = 'en', string $avatar = '', ?UserMeta $meta = null): string
    {
        if (ACL::hasPermission(static::$currentUser, ACL::write('user'))) {
            // Make sure full is assigned
            if ($name->full === '') {
                $name = new Username($name->first, $name->last, $name->first . ' ' . $name->last);
            }

            if ($meta === null) {
                $meta = new UserMeta((object)['flags' => (object)['use2fa' => false]]);
            }

            // Validate email properly
            $this->validateEmail($email, '', true);

            // Validate password
            $valid = Security::validatePassword($password);

            if (!$valid) {
                throw new DatabaseException('Password does not pass minimum security level', 0403);
            }

            $id = $this->insert([
                'name' => $name,
                'email' => $email,
                'status' => true,
                'roles' => $roles,
                'avatar' => $avatar,
                'password' => Security::hashPassword($password),
                'meta' => $meta->simplify(),
                'temporary_token' => '',
                'locale' => $locale
            ]);

            if (!empty($id) && $_ENV['SETTINGS']->get('emails.sendNewAccount', false)) {
                // Send a nice email to greet
                try {
                    $mail = new Mail();
                    $mail->to($email)->useEmail('new_account', $locale)->send();
                    return $id;
                } catch (Exception $e) {
                    return $id;
                }
            }

            return $id;
        }

        return '';
    }

    /**
     *
     * Update a user
     *
     * @param  string|ObjectId  $id
     * @param  Username|null    $name
     * @param  string|null      $email
     * @param  string|null      $password
     * @param  Collection|null  $roles
     * @param  string|null      $avatar
     * @param  UserMeta|null    $meta
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     *
     */
    public function update(string|ObjectId $id, ?Username $name = null, ?string $email = null, ?string $password = null, ?Collection $roles = null, ?string $avatar = '', ?UserMeta $meta = null): bool
    {
        if ((isset(static::$currentUser) && (string)static::$currentUser->_id === $id) || ACL::hasPermission(static::$currentUser, ACL::write('user'))) {
            $update = [];
            $id = $this->ensureObjectId($id);

            if ($name !== null) {
                $update['name'] = $name;
            }

            // Validate email properly
            if ($email !== null && trim($email) !== '') {
                $valid = $this->validateEmail($email, $id, true);
                $update['email'] = $email;
            }

            if ($password !== null && trim($password) !== '') {
                $valid = Security::validatePassword($password);

                if (!$valid) {
                    throw new DatabaseException('Password does not pass minimum security level', 0403);
                }

                $update['password'] = Security::hashPassword($password);
            }

            if ($roles) {
                // Make sure we are not allowing a user with lower role to give higher role (by hack)
                $current = Role::getHighestLevel(static::$currentUser->roles);
                $requested = Role::getHighestLevel($roles);

                if ($current >= $requested) {
                    $update['roles'] = $roles;
                }
            }

            if ($avatar) {
                $update['avatar'] = $avatar;
            }

            if ($meta) {
                $update['meta'] = $meta;
            }

            $this->updateOne(['_id' => $id], ['$set' => $update]);
            return true;
        }

        return false;
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
            $id = $this->ensureObjectId($id);

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
        $user = $this->findOne(['temporary_token' => $token])->exec();

        if ($user) {
            // Set session data, get token
            $session = Session::manager();
            $session->setUserId((string)$user->_id);
            $session->set('user_id', (string)$user->_id);
            $token = $session->get('set_token'); // Only works with stateless

            static::$currentUser = $this->findById((string)$user->_id)->exec();

            // Get role
            $roleModel = new Role();

            // Clear temporary token
            $this->updateOne(['_id' => $user->_id], ['$set' => ['temporary_token' => '']]);

            static::$currentUser->auth_token = $token ?? '';
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
            // Set session data, get token
            $session = Session::manager();
            $session->setUserId((string)$user->_id);
            $session->set('user_id', (string)$user->_id);
            $token = $session->get('set_token'); // Only works with stateless

            $instance = new static();
            static::$currentUser = $instance->findById((string)$user->_id)->exec();

            // Get role
            $roleModel = new Role();
            static::$currentUser->auth_token = $token ?? '';

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

    /**
     *
     * Validate email
     *
     * @param  string  $email
     * @param  string  $id
     * @param  bool    $throw
     * @return bool
     * @throws DatabaseException
     *
     */
    private function validateEmail(string $email, string $id = '', bool $throw = false): bool
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // Check that it does not exist already
            $found = $this->getByEmail($email);

            // Error if the email already used by someone that is not the updated user's id
            if ($found) {
                if ((string)$found->_id !== $id) {
                    if ($throw) {
                        throw new DatabaseException("Cannot use email '{$email}', already in use.", 0403);
                    }

                    return false;
                }
            }

            return true;
        }

        if ($throw) {
            throw new DatabaseException("Email '{$email}' is not a valid email.", 0400);
        }

        return false;
    }

    /**
     *
     * Remove a role from all users
     *
     * @param  string  $role
     * @return void
     * @throws DatabaseException
     *
     */
    public static function removeRoleFromAll(string $role): void
    {
        $instance = new static();
        $instance->updateMany([], ['$pull' => ['roles' => $role]]);
    }
}