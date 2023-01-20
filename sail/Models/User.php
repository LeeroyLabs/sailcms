<?php

namespace SailCMS\Models;

use Exception;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;
use Ramsey\Uuid\Uuid;
use SailCMS\ACL;
use SailCMS\Collection;
use SailCMS\Database\Model;
use SailCMS\Debug;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\EmailException;
use SailCMS\Errors\FileException;
use SailCMS\Errors\PermissionException;
use SailCMS\Event;
use SailCMS\Log;
use SailCMS\Mail;
use SailCMS\Middleware;
use SailCMS\Security;
use SailCMS\Session;
use SailCMS\Types\Listing;
use SailCMS\Types\LoginResult;
use SailCMS\Types\MetaSearch;
use SailCMS\Types\MiddlewareType;
use SailCMS\Types\Pagination;
use SailCMS\Types\QueryOptions;
use SailCMS\Types\UserMeta;
use SailCMS\Types\Username;
use SailCMS\Types\UserSorting;
use SailCMS\Types\UserTypeSearch;
use stdClass;

/**
 *
 *
 * @property Username          $name
 * @property Collection        $roles
 * @property string            $email
 * @property string            $status
 * @property string            $password
 * @property string            $avatar
 * @property UserMeta|stdClass $meta
 * @property string            $temporary_token
 * @property string            $auth_token
 * @property string            $locale
 * @property string            $validation_code
 * @property string            $reset_code
 * @property bool              $validated
 * @property int               $created_at
 *
 */
class User extends Model
{
    protected string $collection = 'users';
    protected array $guards = ['password'];
    protected array $casting = [
        'name' => Username::class,
        'roles' => Collection::class,
        'meta' => UserMeta::class
    ];

    public const EVENT_DELETE = 'event_delete_user';
    public const EVENT_CREATE = 'event_create_user';
    public const EVENT_UPDATE = 'event_update_user';
    public const EVENT_LOGIN = 'event_login_user';

    public static ?User $currentUser = null;

    private static Collection $permsCache;

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
            self::$currentUser = $instance->findById($uid)->exec();

            if (self::$currentUser) {
                self::$currentUser->auth_token = env('jwt', '');

                Event::dispatch(self::EVENT_LOGIN, self::$currentUser);
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
        return (isset(self::$currentUser));
    }

    /**
     *
     * Get user's permission
     *
     * @return Collection
     * @throws DatabaseException
     *
     */
    public function permissions(): Collection
    {
        if (isset(self::$permsCache)) {
            return self::$permsCache;
        }

        $roleModel = new Role();
        $permissions = Collection::init();

        foreach ($this->roles->unwrap() as $roleSlug) {
            $role = $roleModel->getByName($roleSlug);

            if ($role) {
                $permissions->push(...$role->permissions);
            }
        }

        self::$permsCache = $permissions;

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
     * @param  string         $role
     * @param  bool           $createWithSetPassword
     * @return string
     * @throws DatabaseException
     *
     */
    public function createRegularUser(
        Username $name,
        string $email,
        string $password,
        string $locale = 'en',
        string $avatar = '',
        ?UserMeta $meta = null,
        string $role = '',
        bool $createWithSetPassword = false
    ): string {
        // Make sure full is assigned
        if (trim($name->full) === '') {
            $name = new Username($name->first, $name->last);
        }

        if ($meta === null) {
            $meta = new UserMeta((object)[]);
        }

        // Validate name (basic)
        if (empty($name->first) || empty($name->last)) {
            throw new DatabaseException('9001: Name is not valid, please make sure you fill in both first and last name.', 0403);
        }

        // Validate email properly
        $this->validateEmail($email, '', true);

        // Validate password
        if (!$createWithSetPassword) {
            $valid = Security::validatePassword($password);

            if (!$valid) {
                throw new DatabaseException('9002: Password does not pass minimum security level', 0403);
            }
        } else {
            $password = Uuid::uuid4()->toString();
        }

        if ($role !== '') {
            $role = [$role];
        } else {
            $role = setting('users.baseRole', 'general-user');
        }

        $validate = setting('users.requireValidation', true);

        $code = Security::generateVerificationCode();
        $passCode = substr(Security::generateVerificationCode(), 5, 16);

        $id = $this->insert([
            'name' => $name,
            'email' => $email,
            'status' => true,
            'roles' => [$role],
            'avatar' => $avatar,
            'password' => Security::hashPassword($password),
            'meta' => $meta->simplify(),
            'temporary_token' => '',
            'locale' => $locale,
            'validation_code' => $code,
            'validated' => !$validate,
            'reset_code' => ($createWithSetPassword) ? $passCode : '',
            'created_at' => time()
        ]);

        if (!empty($id) && setting('emails.sendNewAccount', false)) {
            // Send a nice email to greet
            try {
                $mail = new Mail();

                if ($createWithSetPassword) {
                    $defaultWho = setting('emails.globalContext')->unwrap()['locales'][$locale]['defaultWho'];

                    $mail->to($email)
                         ->useEmail('new_account_by_proxy', $locale, [
                             'verification_code' => $code,
                             'reset_pass_code' => $passCode,
                             'name' => $name->first,
                             'who' => (self::$currentUser) ? self::$currentUser->name->full : $defaultWho
                         ])
                         ->send();
                } else {
                    $mail->to($email)->useEmail('new_account', $locale, ['verification_code' => $code])->send();
                }

                Event::dispatch(self::EVENT_CREATE, ['id' => (string)$id, 'email' => $email, 'name' => $name]);
                return $id;
            } catch (Exception $e) {
                Log::warning(
                    'Mailing Error ' . $e->getMessage(),
                    [
                        'file' => basename($e->getFile()),
                        'line' => $e->getLine()
                    ]
                );

                Event::dispatch(self::EVENT_CREATE, ['id' => (string)$id, 'email' => $email, 'name' => $name]);
                return $id;
            }
        }

        Event::dispatch(self::EVENT_CREATE, ['id' => (string)$id, 'email' => $email, 'name' => $name]);
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
            } catch (Exception) {
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
            throw new DatabaseException('9001: Name is not valid, please make sure you fill in both first and last name.', 0403);
        }

        // Validate password
        if ($password !== '') {
            $valid = Security::validatePassword($password);

            if (!$valid) {
                throw new DatabaseException('9002: Password does not pass minimum security level', 0403);
            }
        }

        // Make sure full is assigned
        if ($name->full === '') {
            $name = new Username($name->first, $name->last);
        }

        if ($meta === null) {
            $meta = new UserMeta((object)['flags' => (object)['use2fa' => false]]);
        }

        // Validate email properly
        $this->validateEmail($email, '', true);

        $pass = Uuid::uuid4();
        if ($password !== '') {
            $pass = $password;
        }

        $validate = setting('users.requireValidation', true);

        $code = Security::generateVerificationCode();
        $passCode = substr(Security::generateVerificationCode(), 5, 16);

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
            'validated' => !$validate,
            'reset_code' => $passCode,
            'created_at' => time()
        ]);

        if (!empty($id) && setting('emails.sendNewAccount', false)) {
            // Send a nice email to greet
            try {
                // Overwrite the cta url for the admin one
                $mail = new Mail();
                $mail->to($email)->useEmail(
                    'new_admin_account',
                    $locale,
                    [
                        'verification_code' => $code,
                        'reset_pass_code' => $passCode,
                        'name' => $name->first
                    ]
                )->send();

                Event::dispatch(self::EVENT_CREATE, ['id' => $id, 'email' => $email, 'name' => $name]);
                return $id;
            } catch (Exception $e) {
                // Logging error
                Log::warning(
                    'Mailing Error ' . $e->getMessage(),
                    [
                        'file' => basename($e->getFile()),
                        'line' => $e->getLine()
                    ]
                );

                Event::dispatch(self::EVENT_CREATE, ['id' => $id, 'email' => $email, 'name' => $name]);
                return $id;
            }
        }

        Event::dispatch(self::EVENT_CREATE, ['id' => $id, 'email' => $email, 'name' => $name]);
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
     * @param  string           $locale
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function update(
        string|ObjectId $id,
        ?Username $name = null,
        ?string $email = null,
        ?string $password = null,
        ?Collection $roles = null,
        ?string $avatar = '',
        ?UserMeta $meta = null,
        string $locale = ''
    ): bool {
        $this->hasPermissions(false, true, $id);

        $update = [];
        $id = $this->ensureObjectId($id);

        if ($name !== null) {
            $update['name'] = $name;
        }

        if ($locale !== '') {
            $update['locale'] = $locale;
        }

        // Validate email properly
        if ($email !== null && trim($email) !== '') {
            $this->validateEmail($email, $id, true);
            $update['email'] = $email;
        }

        if ($password !== null && trim($password) !== '') {
            $valid = Security::validatePassword($password);

            if (!$valid) {
                throw new DatabaseException('9002: Password does not pass minimum security level', 0403);
            }

            $update['password'] = Security::hashPassword($password);
        }

        if ($roles) {
            // Make sure we are not allowing a user with lower role to give higher role (by hack)
            $current = Role::getHighestLevel(self::$currentUser->roles);
            $requested = Role::getHighestLevel($roles);

            if ($current >= $requested) {
                $update['roles'] = $roles;
            }
        }

        if ($avatar) {
            $update['avatar'] = $avatar;
        }

        if ($meta) {
            $update['meta'] = $meta->simplify();
        }

        $this->updateOne(['_id' => $id], ['$set' => $update]);
        Event::dispatch(self::EVENT_UPDATE, ['id' => $id, 'update' => $update]);
        return true;
    }

    /**
     *
     * Get a list of users
     *
     * @param  int                  $page
     * @param  int                  $limit
     * @param  string               $search
     * @param  UserSorting|null     $sorting
     * @param  UserTypeSearch|null  $typeSearch
     * @param  MetaSearch|null      $metaSearch
     * @param  bool|null            $status
     * @param  bool|null            $validated
     * @return Listing
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function getList(
        int $page = 0,
        int $limit = 25,
        string $search = '',
        UserSorting $sorting = null,
        UserTypeSearch|null $typeSearch = null,
        MetaSearch|null $metaSearch = null,
        bool|null $status = null,
        bool|null $validated = null
    ): Listing {
        $this->hasPermissions(true);

        if (!isset($sorting)) {
            $sorting = new UserSorting('name.full', 'asc');
        }

        $offset = $page * $limit - $limit; // (ex: 1 * 25 - 25 = 0 offset)
        $options = QueryOptions::initWithSort([$sorting->sort => $sorting->order]);
        $options->skip = $offset;
        $options->limit = ($limit > 100) ? 25 : $limit;

        // Sorting required collation
        $options->collation = 'en';

        $query = [];

        if (!empty($search)) {
            $query = [
                '$or' => [
                    ['name.full' => new Regex($search, 'gi')],
                    ['email' => $search]
                ]
            ];
        }

        // User Type Search Filter
        if ($typeSearch) {
            $query['roles'] = ['$in' => explode(',', $typeSearch->type)];

            if ($typeSearch->except) {
                $query['roles'] = ['$nin' => explode(',', $typeSearch->type)];
            }
        }

        // Meta Search Filter
        if ($metaSearch) {
            $query['meta.' . $metaSearch->key] = new Regex($metaSearch->value, 'gi');
        }

        if (isset($status)) {
            $query['status'] = $status;
        }

        if (isset($validated)) {
            $query['validated'] = $validated;
        }

        // Pagination
        $total = $this->count($query);
        $pages = ceil($total / $limit);
        $current = $page;
        $pagination = new Pagination($current, (int)$pages, $total);

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
        Event::dispatch(self::EVENT_DELETE, (string)$this->_id);
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
        Event::dispatch(self::EVENT_DELETE, (string)$id);
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
        Event::dispatch(self::EVENT_DELETE, $email);
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
        $user = $this->findOne(['email' => $email])->exec();

        if (!$user) {
            return new LoginResult('', 'error');
        }

        if (!$user->validated) {
            return new LoginResult('', 'not-validated');
        }

        $data = new Middleware\Data(Middleware\Login::LogIn, ['email' => $email, 'password' => $password, 'allowed' => false]);
        $mwResult = Middleware::execute(MiddlewareType::LOGIN, $data);

        if (!$mwResult->data['allowed']) {
            if (Security::verifyPassword($password, $user->password)) {
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

            self::$currentUser = $this->findById((string)$user->_id)->exec();

            // Clear temporary token
            $this->updateOne(['_id' => $user->_id], ['$set' => ['temporary_token' => '']]);

            self::$currentUser->auth_token = $token ?? '';

            Event::dispatch(self::EVENT_LOGIN, self::$currentUser);
            return self::$currentUser;
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

        $user = $this->findOne(['email' => $email, 'validated' => true])->exec();
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
            self::$currentUser = $instance->findById((string)$user->_id)->exec();

            // Get role
            self::$currentUser->auth_token = $token ?? '';

            Event::dispatch(self::EVENT_LOGIN, self::$currentUser);

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
        self::$currentUser = null;
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

            self::$currentUser = $user;

            // Get role
            self::$currentUser->auth_token = $token ?? '';

            return self::$currentUser;
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
        $data = new Middleware\Data(Middleware\Login::ForgotPassword, ['email' => $email, 'allowed' => true]);
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
            throw new DatabaseException('9002: Password does not pass minimum security level', 0403);
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
     * Get a list of users by their ids.
     *
     * Note:
     * This call is not permission protected because it would require some use case to give too much power
     * to a normal user.
     *
     * @param  array|Collection  $ids
     * @return Collection
     * @throws DatabaseException
     *
     */
    public function getListByIds(array|Collection $ids): Collection
    {
        $list = [];

        if (is_object($ids)) {
            $ids = $ids->unwrap();
        }

        foreach ($ids as $id) {
            $list[] = $this->ensureObjectId($id);
        }

        return new Collection($this->find(['_id' => ['$in' => $list]])->exec());
    }

    /**
     *
     * Validate email
     *
     * @param  string  $email
     * @param  string  $id
     * @param  bool    $throw
     * @return void
     * @throws DatabaseException
     *
     */
    private function validateEmail(string $email, string $id = '', bool $throw = false): void
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // Check that it does not exist already
            $found = $this->getByEmail($email);

            // Error if the email already used by someone that is not the updated user's id
            if ($found && (string)$found->_id !== $id) {
                if ($throw) {
                    throw new DatabaseException("9001: Cannot use email '{$email}', already in use.", 0403);
                }

                return;
            }

            return;
        }

        if ($throw) {
            throw new DatabaseException("9003: Email '{$email}' is not a valid email.", 0400);
        }
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
            if ((isset(self::$currentUser) && (string)self::$currentUser->_id === $id)) {
                if ($read) {
                    if (!ACL::hasPermission(self::$currentUser, ACL::read('user'))) {
                        throw new PermissionException('0403: Permission Denied', 0403);
                    }
                } elseif (!ACL::hasPermission(self::$currentUser, ACL::write('user'))) {
                    throw new PermissionException('0403: Permission Denied', 0403);
                }
            }
        } elseif ($read) {
            if (!ACL::hasPermission(self::$currentUser, ACL::read('user'))) {
                throw new PermissionException('0403: Permission Denied', 0403);
            }
        } elseif (!ACL::hasPermission(self::$currentUser, ACL::write('user'))) {
            throw new PermissionException('0403: Permission Denied', 0403);
        }
    }
}