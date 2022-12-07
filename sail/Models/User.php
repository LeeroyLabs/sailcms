<?php

namespace SailCMS\Models;

use Exception;
use JsonException;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;
use Ramsey\Uuid\Uuid;
use SailCMS\ACL;
use SailCMS\Collection;
use SailCMS\Database\Model;
use SailCMS\Debug;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\EmailException;
use SailCMS\Errors\FileException;
use SailCMS\Errors\PermissionException;
use SailCMS\Event;
use SailCMS\Mail;
use SailCMS\Middleware;
use SailCMS\Security;
use SailCMS\Session;
use SailCMS\Types\Listing;
use SailCMS\Types\LoginResult;
use SailCMS\Types\MiddlewareType;
use SailCMS\Types\Pagination;
use SailCMS\Types\QueryOptions;
use SailCMS\Types\UserMeta;
use SailCMS\Types\Username;

class User extends Model
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
    public string $validation_code;
    public string $reset_code;
    public bool $validated;

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
                'locale',
                'validation_code',
                'validated',
                'reset_code',
                'created_at'
            ];
        }

        return [
            '_id',
            'name',
            'roles',
            'email',
            'status',
            'avatar',
            'meta',
            'temporary_token',
            'locale',
            'validation_code',
            'validated',
            'reset_code',
            'created_at'
        ];
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

            if (static::$currentUser) {
                static::$currentUser->auth_token = env('jwt', '');
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
        $permissions = Collection::init();

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
     * @throws PermissionException
     *
     */
    public function getById(string $id): ?User
    {
        $this->hasPermissions(true, true, $id);
        return $this->findById($id)->exec();
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
     * @throws ACLException
     * @throws PermissionException
     *
     */
    public function createRegularUser(Username $name, string $email, string $password, string $locale = 'en', string $avatar = '', ?UserMeta $meta = null): string
    {
        // Make sure full is assigned
        if (trim($name->full) === '') {
            $name = new Username($name->first, $name->last, $name->first . ' ' . $name->last);
        }

        if ($meta === null) {
            $meta = new UserMeta((object)[]);
        }

        // Validate name (basic)
        if (empty($name->first) || empty($name->last)) {
            throw new DatabaseException('Name is not valid, please make sure you fill in both first and last name.', 0403);
        }

        // Validate email properly
        $this->validateEmail($email, '', true);

        // Validate password
        $valid = Security::validatePassword($password);

        if (!$valid) {
            throw new DatabaseException('Password does not pass minimum security level', 0403);
        }

        $code = Security::generateVerificationCode();

        $id = $this->insert([
            'name' => $name,
            'email' => $email,
            'status' => true,
            'roles' => ['general-user'],
            'avatar' => $avatar,
            'password' => Security::hashPassword($password),
            'meta' => $meta->simplify(),
            'temporary_token' => '',
            'locale' => $locale,
            'validation_code' => $code,
            'validated' => false,
            'reset_code' => '',
            'created_at' => time()
        ]);

        if (!empty($id) && setting('emails.sendNewAccount', false)) {
            // Send a nice email to greet
            try {
                $mail = new Mail();
                $mail->to($email)->useEmail('new_account', $locale, ['verification_code' => $code])->send();
                Event::dispatch(static::EVENT_CREATE, ['id' => $id, 'email' => $email, 'name' => $name]);
                return $id;
            } catch (Exception $e) {
                Event::dispatch(static::EVENT_CREATE, ['id' => $id, 'email' => $email, 'name' => $name]);
                return $id;
            }
        }

        Event::dispatch(static::EVENT_CREATE, ['id' => $id, 'email' => $email, 'name' => $name]);
        return $id;
    }

    /**
     *
     * Resend a validation email
     *
     * @param  string  $email
     * @return bool
     * @return bool
     *
     * @throws DatabaseException
     */
    public function resendValidationEmail(string $email): bool
    {
        $user = $this->findOne(['email' => $email])->exec();

        if ($user) {
            try {
                $mail = new Mail();
                $mail->to($email)->useEmail('new_account', $user->locale, ['verification_code' => $user->validation_code])->send();
                return true;
            } catch (Exception $e) {
                return false;
            }
        }

        return false;
    }

    /**
     *
     * Create a new user
     *
     * @param  Username          $name
     * @param  string            $email
     * @param  string            $password
     * @param  Collection|array  $roles
     * @param  string            $locale
     * @param  string            $avatar
     * @param  UserMeta|null     $meta
     * @return string
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     */
    public function create(Username $name, string $email, string $password, Collection|array $roles, string $locale = 'en', string $avatar = '', ?UserMeta $meta = null): string
    {
        $this->hasPermissions();

        if (is_array($roles)) {
            $roles = new Collection($roles);
        }

        // Validate name (basic)
        if (empty($name->first) || empty($name->last)) {
            throw new DatabaseException('Name is not valid, please make sure you fill in both first and last name.', 0403);
        }

        // Validate password
        if ($password !== '') {
            $valid = Security::validatePassword($password);

            if (!$valid) {
                throw new DatabaseException('Password does not pass minimum security level', 0403);
            }
        }

        // Make sure full is assigned
        if ($name->full === '') {
            $name = new Username($name->first, $name->last, $name->first . ' ' . $name->last);
        }

        if ($meta === null) {
            $meta = new UserMeta((object)['flags' => (object)['use2fa' => false]]);
        }

        // Validate email properly
        $this->validateEmail($email, '', true);

        $code = Security::generateVerificationCode();

        $pass = Uuid::uuid4();
        if ($password !== '') {
            $pass = $password;
        }

        $id = $this->insert([
            'name' => $name,
            'email' => $email,
            'status' => true,
            'roles' => $roles,
            'avatar' => $avatar,
            'password' => Security::hashPassword($pass),
            'meta' => $meta->simplify(),
            'temporary_token' => '',
            'locale' => $locale,
            'validation_code' => $code,
            'validated' => false,
            'reset_code' => '',
            'created_at' => time()
        ]);

        if (!empty($id) && setting('emails.sendNewAccount', false)) {
            // Send a nice email to greet
            try {
                // Overwrite the cta url for the admin one
                $url = setting('adminTrigger', 'admin') . '/validate/' . $code;

                $mail = new Mail();
                $mail->to($email)->useEmail('new_account', $locale, ['verification_code' => $url, 'name' => $name->first])->send();
                return $id;
            } catch (Exception $e) {
                return $id;
            }
        }

        Event::dispatch(static::EVENT_CREATE, ['id' => $id, 'email' => $email, 'name' => $name]);
        return $id;
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
     * @throws PermissionException
     *
     */
    public function update(string|ObjectId $id, ?Username $name = null, ?string $email = null, ?string $password = null, ?Collection $roles = null, ?string $avatar = '', ?UserMeta $meta = null): bool
    {
        $this->hasPermissions(false, true, $id);

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
        Event::dispatch(static::EVENT_UPDATE, ['id' => $id, 'update' => $update]);
        return true;
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
     * @param  string  $user_type
     * @return Listing
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function getList(int $page = 0, int $limit = 25, string $search = '', string $sort = 'name.first', int $direction = Model::SORT_ASC, string $user_type = ''): Listing
    {
        $this->hasPermissions(true);

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

        if ($user_type !== '') {
            $query['roles'] = $user_type;
        }

        // Pagination
        $total = $this->count($query);
        $pages = ceil($total / $limit);
        $current = $page;
        $pagination = new Pagination($current, $pages, $total);

        $list = $this->find($query, $options)->exec();

        return new Listing($pagination, new Collection($list));
    }

    /**
     *
     * Delete the user from this instance
     *
     * @return bool
     * @throws DatabaseException
     * @throws ACLException
     * @throws PermissionException
     *
     */
    public function remove(): bool
    {
        $this->hasPermissions();

        $this->deleteById($this->_id);
        Event::dispatch(static::EVENT_DELETE, (string)$this->_id);
        return true;
    }

    /**
     *
     * Delete a user by his id
     *
     * @param  string|ObjectId  $id
     * @return bool
     * @throws DatabaseException
     * @throws ACLException
     * @throws PermissionException
     *
     */
    public function removeById(string|ObjectId $id): bool
    {
        $this->hasPermissions();

        $id = $this->ensureObjectId($id);
        $this->deleteById($id);
        Event::dispatch(static::EVENT_DELETE, (string)$id);
        return true;
    }

    /**
     *
     * Delete a user by his email
     *
     * @param  string  $email
     * @return bool
     * @throws DatabaseException
     * @throws ACLException
     * @throws PermissionException
     *
     */
    public function removeByEmail(string $email): bool
    {
        $this->hasPermissions();

        $this->deleteOne(['email' => $email]);
        Event::dispatch(static::EVENT_DELETE, $email);
        return true;
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
     * @return LoginResult
     * @throws DatabaseException
     *
     */
    public function verifyUserPass(string $email, string $password): LoginResult
    {
        $user = $this->findOne(['email' => $email, 'validated' => true])->allFields()->exec();

        $data = new Middleware\Data(Middleware\Login::LogIn, ['email' => $email, 'password' => $password, 'allowed' => false]);
        $mwResult = Middleware::execute(MiddlewareType::LOGIN, $data);

        if (!$mwResult->data['allowed']) {
            if ($user && Security::verifyPassword($password, $user->password)) {
                if ($user->meta->flags->use2fa) {
                    return new LoginResult((string)$user->_id, '2fa');
                }

                $key = Security::secureTemporaryKey();
                $this->updateOne(['_id' => $user->_id], ['$set' => ['temporary_token' => $key]]);
                return new LoginResult((string)$user->_id, $key);
            }
        } else {
            // Middleware says everything is ok, login
            if ($user->meta->flags->use2fa) {
                return new LoginResult((string)$user->_id, '2fa');
            }

            $key = Security::secureTemporaryKey();
            $this->updateOne(['email' => $email], ['$set' => ['temporary_token' => $key]]);
            return new LoginResult((string)$user->_id, $key);
        }

        return new LoginResult('', 'error');
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
        $user = $this->findOne(['temporary_token' => $token, 'validated' => true])->exec();

        if ($user) {
            // Set session data, get token
            $session = Session::manager();
            $session->setUserId((string)$user->_id);
            $session->set('user_id', (string)$user->_id);
            $token = $session->get('set_token'); // Only works with stateless

            static::$currentUser = $this->findById((string)$user->_id)->exec();

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
        $data = new Middleware\Data(Middleware\Login::LogIn, ['email' => $email, 'password' => $password, 'allowed' => false]);
        $mwResult = Middleware::execute(MiddlewareType::LOGIN, $data);

        $user = $this->findOne(['email' => $email, 'validated' => true])->allFields()->exec('', 0);
        $pass = false;

        if (!$mwResult->data['allowed']) {
            if ($user && Security::verifyPassword($password, $user->password)) {
                $pass = true;
            }
        } else {
            $pass = true;
        }

        if ($pass) {
            // Set session data, get token
            $session = Session::manager();
            $session->setUserId((string)$user->_id);
            $session->set('user_id', (string)$user->_id);
            $token = $session->get('set_token'); // Only works with stateless

            $instance = new static();
            static::$currentUser = $instance->findById((string)$user->_id)->exec();

            // Get role
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
     * @throws PermissionException
     *
     */
    public static function flagged(string $flag): Collection
    {
        $instance = new static();
        $instance->hasPermissions(true);
        return new Collection($instance->find(["meta.flags.{$flag}" => ['$exists' => true, '$eq' => true]])->exec());
    }

    /**
     *
     * Get users who are not flagged with the given flag
     *
     * @param  string  $flag
     * @return Collection
     * @throws DatabaseException
     * @throws ACLException
     * @throws PermissionException
     *
     */
    public static function notFlagged(string $flag): Collection
    {
        $instance = new static();
        $instance->hasPermissions(true);
        return new Collection($instance->find([
            '$or' => [
                ["meta.flags.{$flag}" => ['$exists' => false]],
                ["meta.flags.{$flag}" => false]
            ]
        ])->exec());
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

    /**
     *
     * Validate an account with the given code
     *
     * @param  string  $code
     * @return bool
     * @throws DatabaseException
     *
     */
    public static function validateWithCode(string $code): bool
    {
        $instance = new static();

        $record = $instance->findOne(['validation_code' => $code])->exec();

        if ($record) {
            $instance->updateOne(['validation_code' => $code], ['$set' => ['validation_code' => '', 'validated' => true]]);
            return true;
        }

        return false;
    }

    /**
     *
     * Login a user that was allowed to be by the 2FA rescue system
     *
     * @param  string  $id
     * @return User|null
     * @throws DatabaseException
     *
     */
    public static function loginFromRescue(string $id): ?User
    {
        $instance = new static();
        $user = $instance->findById($id)->exec();

        if ($user) {
            $session = Session::manager();
            $session->setUserId((string)$user->_id);
            $session->set('user_id', (string)$user->_id);
            $token = $session->get('set_token'); // Only works with stateless

            static::$currentUser = $user;

            // Get role
            static::$currentUser->auth_token = $token ?? '';

            return static::$currentUser;
        }

        return null;
    }

    /**
     *
     * Forgot password handler
     *
     * @param  string  $email
     * @return bool
     * @throws DatabaseException
     * @throws FileException
     * @throws EmailException
     *
     */
    public static function forgotPassword(string $email): bool
    {
        $data = new Middleware\Data(Middleware\Login::ForgotPassword, ['email' => $email, 'allowed' => false]);
        $mwResult = Middleware::execute(MiddlewareType::LOGIN, $data);

        if ($mwResult->data['allowed']) {
            $instance = new static();
            $record = $instance->findOne(['email' => $email, 'validated' => true])->exec();

            if ($record) {
                $code = substr(Security::generateVerificationCode(), 5, 16);
                $record->updateOne(['_id' => $record->_id], ['$set' => ['reset_code' => $code]]);

                $mail = new Mail();
                $mail->to($email)->useEmail(
                    'reset_password',
                    $record->locale,
                    [
                        'reset_code' => $code,
                        'name' => $record->name->first
                    ]
                )->send();
                return true;
            }

            // Always return true to misdirect potential attackers
            return true;
        }

        // This is only returned if the middleware tells us NO!
        return false;
    }

    /**
     *
     * Change the password using the reset code
     *
     * @param  string  $code
     * @param  string  $password
     * @return bool
     * @throws DatabaseException
     *
     */
    public static function changePassword(string $code, string $password): bool
    {
        $instance = new static();

        // Validate password
        $valid = Security::validatePassword($password);

        if (!$valid) {
            throw new DatabaseException('Password does not pass minimum security level', 0403);
        }

        $instance->updateOne(['reset_code' => $code],
            [
                '$set' => [
                    'password' => Security::hashPassword($password),
                    'reset_code' => ''
                ]
            ]
        );

        return true;
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
     * @throws PermissionException
     * @throws ACLException
     *
     */
    private function validateEmail(string $email, string $id = '', bool $throw = false): bool
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // Check that it does not exist already
            $found = $this->getByEmail($email);

            // Error if the email already used by someone that is not the updated user's id
            if ($found && (string)$found->_id !== $id) {
                if ($throw) {
                    throw new DatabaseException("Cannot use email '{$email}', already in use.", 0403);
                }

                return false;
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
     * Override the BaseModel for a more complex version
     *
     * @param  bool         $read
     * @param  bool         $advanced
     * @param  string|null  $id
     * @return void
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    protected function hasPermissions(bool $read = false, bool $advanced = false, string|null $id = null): void
    {
        if ($advanced) {
            if ((isset(static::$currentUser) && (string)static::$currentUser->_id === $id)) {
                if ($read) {
                    if (!ACL::hasPermission(static::$currentUser, ACL::read('user'))) {
                        throw new PermissionException('Permission Denied', 0403);
                    }
                } elseif (!ACL::hasPermission(static::$currentUser, ACL::write('user'))) {
                    throw new PermissionException('Permission Denied', 0403);
                }
            }
        } elseif ($read) {
            if (!ACL::hasPermission(static::$currentUser, ACL::read('user'))) {
                throw new PermissionException('Permission Denied', 0403);
            }
        } elseif (!ACL::hasPermission(static::$currentUser, ACL::write('user'))) {
            throw new PermissionException('Permission Denied', 0403);
        }
    }
}