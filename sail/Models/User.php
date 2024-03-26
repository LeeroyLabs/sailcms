<?php

namespace SailCMS\Models;

use Exception;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;
use Ramsey\Uuid\Uuid;
use SailCMS\ACL;
use SailCMS\Collection;
use SailCMS\Database\Model;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\EmailException;
use SailCMS\Errors\FileException;
use SailCMS\Errors\PermissionException;
use SailCMS\Event;
use SailCMS\Locale;
use SailCMS\Log;
use SailCMS\Mail;
use SailCMS\Middleware;
use SailCMS\Sail;
use SailCMS\Security;
use SailCMS\Session;
use SailCMS\Text;
use SailCMS\Types\Listing;
use SailCMS\Types\LoginResult;
use SailCMS\Types\MetaSearch;
use SailCMS\Types\MiddlewareType;
use SailCMS\Types\Pagination;
use SailCMS\Types\PasswordChangeResult;
use SailCMS\Types\QueryOptions;
use SailCMS\Types\UserMeta;
use SailCMS\Types\Username;
use SailCMS\Types\UserSorting;
use SailCMS\Types\UserTypeSearch;
use SensitiveParameter;
use stdClass;
use Twig\Error\LoaderError;

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
 * @property string            $group
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

    private const ANONYMOUS_EMAIL = 'anonymous@mail.io';

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
                $permissions = $permissions->merge($role->permissions);
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
     * @param  bool    $api
     * @return User|null
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function getById(string $id, bool $api = true): ?User
    {
        if ($api) {
            $this->hasPermissions(true, true, $id);
        }

        return $this->findById($id)->exec();
    }

    /**
     *
     * Get a user by id but skip permission checking
     *
     * @param  string  $id
     * @return User|null
     * @throws DatabaseException
     *
     */
    public function getByIdWithoutPermission(string $id): ?User
    {
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
     * Get List of users with given query and settings
     *
     * @param  array   $query
     * @param  string  $sort
     * @param  int     $order
     * @param  int     $page
     * @param  int     $limit
     * @param  string  $collation
     * @return Listing
     * @throws DatabaseException
     *
     */
    public function getListBy(array $query, string $sort = 'full.name', int $order = 1, int $page = 1, int $limit = 25, string $collation = 'en'): Listing
    {
        $skip = $page * $limit - $limit;
        $list = new Collection(
            $this->find($query)
                 ->skip($skip)
                 ->limit($limit)
                 ->collation($collation)
                 ->sort([$sort => $order])
                 ->exec()
        );

        $total = $this->count($query);
        $pages = (int)ceil($total / $limit);
        $pagination = new Pagination($page, $pages, $total);
        return new Listing($pagination, $list);
    }

    /**
     *
     * Create a regular user (usually user from the site) with no roles.
     *
     * @param  Username          $name
     * @param  string            $email
     * @param  string            $password
     * @param  string            $locale
     * @param  string            $avatar
     * @param  UserMeta|null     $meta
     * @param  Collection|array  $roles
     * @param  string            $group
     * @param  bool              $createWithSetPassword
     * @param  string            $emailTemplate
     * @return string
     * @throws DatabaseException
     *
     */
    public function createRegularUser(
        Username $name,
        string $email,
        #[SensitiveParameter] string $password,
        string $locale = 'en',
        string $avatar = '',
        ?UserMeta $meta = null,
        Collection|array $roles = ['general-user'],
        string $group = '',
        bool $createWithSetPassword = false,
        string $emailTemplate = ''
    ): string {
        // Make sure full is assigned
        if (trim($name->full) === '') {
            $name = new Username($name->first, $name->last);
        }

        // Make sure we have an array
        if (is_object($roles)) {
            $roles = $roles->unwrap();
        }

        if ($meta === null) {
            $meta = new UserMeta((object)[]);
        }

        // Validate name (basic)
        if (empty($name->first) || empty($name->last)) {
            throw new DatabaseException('9001: Name is not valid, please make sure you fill in both first and last name.', 0403);
        }

        // Validate email properly
        $email = strtolower($email);
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

        if (empty($roles)) {
            $roles = [setting('users.baseRole', 'general-user')];
        }

        $validate = setting('users.requireValidation', true);
        $code = Security::generateVerificationCode();
        $passCode = substr(Security::generateVerificationCode(), 5, 16);

        $id = $this->insert([
            'name' => $name,
            'email' => $email,
            'status' => true,
            'roles' => new Collection($roles),
            'avatar' => $avatar,
            'password' => Security::hashPassword($password),
            'meta' => $meta->simplify(),
            'temporary_token' => '',
            'locale' => $locale,
            'validation_code' => $code,
            'validated' => !$validate,
            'reset_code' => ($createWithSetPassword) ? $passCode : '',
            'created_at' => time(),
            'group' => $group
        ]);

        if (!empty($id) && setting('emails.sendNewAccount', false)) {
            // Send a nice email to greet
            try {
                $mail = new Mail();
                $defaultWho = setting('emails.globalContext')->unwrap()['locales'][$locale]['defaultWho'];
                $who = (self::$currentUser) ? self::$currentUser->name->full : $defaultWho;

                if ($createWithSetPassword) {
                    $emailName = ($emailTemplate !== '') ? $emailTemplate : 'new_account_by_proxy';

                    $mail->to($email)
                         ->useEmail(
                             2,
                             $emailName, $locale, [
                             'replacements' => [
                                 'name' => $name->first,
                                 'who' => $who
                             ],
                             'verification_code' => $code,
                             'reset_pass_code' => $passCode,
                             'user_email' => $email,
                             'name' => $name->first,
                             'who' => $who
                         ])
                         ->send();
                } else {
                    $emailName = ($emailTemplate !== '') ? $emailTemplate : 'new_account';
                    $mail->to($email)
                         ->useEmail(2, $emailName, $locale, [
                             'replacements' => [
                                 'name' => $name->first,
                                 'who' => $who
                             ],
                             'user_email' => $email,
                             'reset_pass_code' => $passCode,
                             'verification_code' => $code,
                             'who' => $who
                         ])
                         ->send();
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
     *
     */
    public function resendValidationEmail(string $email): bool
    {
        $user = $this->findOne(['email' => $email])->exec();

        if ($user) {
            try {
                $mail = new Mail();
                $mail->to($email)->useEmail(2, 'new_account', $user->locale, ['verification_code' => $user->validation_code])->send();
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
     * @param  bool              $preActivated
     * @return string
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function create(Username $name, string $email, #[SensitiveParameter] string $password, Collection|array $roles, string $locale = 'en', string $avatar = '', ?UserMeta $meta = null, bool $preActivated = false): string
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
            $meta = new UserMeta((object)['flags' => (object)['useMFA' => false]]);
        }

        // Validate email properly
        $email = strtolower($email);
        $this->validateEmail($email, '', true);

        $pass = Uuid::uuid4();
        if ($password !== '') {
            $pass = $password;
        }

        $validate = setting('users.requireValidation', true);
        $actualValidation = false;

        if ($preActivated) {
            $actualValidation = true;
        } elseif (!$validate) {
            $actualValidation = true;
        }

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
            'validated' => $actualValidation,
            'reset_code' => $passCode,
            'created_at' => time(),
            'group' => ''
        ]);

        if (!empty($id) && setting('emails.sendNewAccount', false)) {
            // Send a nice email to greet
            $defaultWho = setting('emails.globalContext')->unwrap()['locales'][$locale]['defaultWho'];
            $who = (self::$currentUser) ? self::$currentUser->name->full : $defaultWho;

            try {
                // Overwrite the cta url for the admin one
                $mail = new Mail();
                $mail->to($email)->useEmail(
                    2,
                    'new_admin_account',
                    $locale,
                    [
                        'replacements' => [
                            'name' => $name->first,
                            'who' => $who
                        ],
                        'verification_code' => $code,
                        'reset_pass_code' => $passCode,
                        'user_email' => $email,
                        'name' => $name->first,
                        'who' => $who
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
        #[SensitiveParameter] ?string $password = null,
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
            $email = strtolower($email);
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

        $update['avatar'] = $avatar ?? '';

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
     * @param  string               $groupId
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
        bool|null $validated = null,
        string $groupId = ''
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

        // Never show the anonymous user
        $query['email'] = ['$ne' => 'anonymous@mail.io'];

        // Filter by group
        if ($groupId !== '') {
            $query['group'] = $groupId;
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
     * Get user count of given group
     *
     * @param  string  $groupId
     * @return int
     *
     */
    public static function countForGroup(string $groupId): int
    {
        return self::query()->count(['group' => $groupId]);
    }

    /**
     *
     * Count how many users for give role
     *
     * @param  string  $role
     * @return int
     *
     */
    public static function countForRole(string $role): int
    {
        return self::query()->count(['roles' => $role]);
    }

    /**
     *
     * Check if user is part of given group (id or slug)
     *
     * @param  string  $group
     * @return bool
     * @throws DatabaseException
     *
     */
    public function partOf(string $group): bool
    {
        if ($group === '') {
            return false;
        }

        if ($this->isValidId($group)) {
            return ($this->group === $group);
        }

        $groupObj = UserGroup::getBy('slug', $group);

        if ($groupObj) {
            return ($this->group === $groupObj->id);
        }

        return false;
    }

    /**
     *
     * Check if user is part of one of the given groups
     *
     * @param  array|Collection  $groups
     * @return bool
     * @throws DatabaseException
     *
     */
    public function partOfAny(array|Collection $groups): bool
    {
        $idList = [];

        foreach ($groups as $group) {
            if ($this->isValidId($group)) {
                $idList[] = $group;
            } elseif ($group !== '') {
                $g = UserGroup::getBy('slug', $group);

                if ($g) {
                    $idList[] = $g->id;
                }
            }
        }

        return in_array($this->group, $idList, true);
    }

    /**
     *
     * Activerecord style add to group
     *
     * @param  string  $groupId
     * @return void
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function setGroup(string $groupId): void
    {
        $this->hasPermissions();
        $this->group = $groupId;
        $this->save();
    }

    /**
     *
     * Activerecord style remove group
     *
     * @return void
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function removeGroup(): void
    {
        $this->hasPermissions();
        $this->group = '';
        $this->save();
    }

    /**
     *
     * Add a user to a group
     *
     * @param  string  $userId
     * @param  string  $groupId
     * @return void
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public static function addToGroup(string $userId, string $groupId): void
    {
        $instance = new self;
        $instance->hasPermissions();

        self::query()->updateOne(['_id' => self::query()->ensureObjectId($userId)], ['$set' => ['group' => $groupId]]);
    }

    /**
     *
     * Remove a user from its group
     *
     * @param  string  $userId
     * @return void
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public static function removeFromGroup(string $userId): void
    {
        $instance = new self;
        $instance->hasPermissions();

        self::query()->updateOne(['_id' => self::query()->ensureObjectId($userId)], ['$set' => ['group' => '']]);
    }

    /**
     *
     * Delete the user from this instance
     *
     * @return bool
     * @throws DatabaseException
     * @throws ACLException
     * @throws PermissionException
     * @throws Exception
     *
     */
    public function remove(): bool
    {
        $this->hasPermissions();

        if (self::anonymousUser()->_id === $this->_id) {
            throw new DatabaseException('9004: Anonymous user cannot be deleted.');
        }

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

        // Do not delete anonymous user
        if ((string)$id === (string)self::anonymousUser()->_id) {
            throw new DatabaseException('9004: Anonymous user cannot be deleted.');
        }

        // Are we allowed to delete the user?
        $currentLevel = Role::getHighestLevel(self::$currentUser->roles);
        $user = self::get($id);

        if (!$user) {
            return false;
        }

        $userRole = Role::getHighestLevel($user->roles);

        if ($userRole > $currentLevel) {
            return false;
        }

        $id = $this->ensureObjectId($id);
        $this->deleteById($id);
        Event::dispatch(self::EVENT_DELETE, (string)$id);
        return true;
    }

    /**
     *
     * Delete a list of users
     *
     * @param  array|Collection  $ids
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function removeByIdList(array|Collection $ids): bool
    {
        $this->hasPermissions();

        if (is_object($ids)) {
            $ids = $ids->unwrap();
        }

        $anonymous = (string)self::anonymousUser()->_id;

        // Remove anonymous from array of ids
        if (in_array($anonymous, $ids, true)) {
            array_filter($ids, function ($id) use ($anonymous)
            {
                return ($id !== $anonymous);
            });
        }

        // Are we allowed to delete the user?
        $currentLevel = Role::getHighestLevel(self::$currentUser->roles);

        // Remove all users that are not allowed (higher level)
        foreach ($ids as $num => $id) {
            $user = self::get($id);

            if (!$user) {
                unset($ids[$num]);
                continue;
            }

            $userRole = Role::getHighestLevel($user->roles);

            if ($userRole > $currentLevel) {
                unset($ids[$num]);
            }
        }

        $ids = array_values($ids);

        // Nothing to delete
        if (count($ids) === 0) {
            return false;
        }

        $list = $ids;
        $ids = $this->ensureObjectIds($ids)->unwrap();
        $this->deleteMany(['_id' => ['$in' => $ids]]);

        Event::dispatch(self::EVENT_DELETE, $list);
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

        if ($email === self::ANONYMOUS_EMAIL) {
            throw new DatabaseException('9004: Anonymous user cannot be deleted.');
        }

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
                $key = Security::secureTemporaryKey();
                $this->updateOne(['_id' => $user->_id], ['$set' => ['temporary_token' => $key]]);

                if ($user->meta->flags->useMFA) {
                    return new LoginResult((string)$user->_id, '2fa');
                }

                return new LoginResult((string)$user->_id, $key);
            }
        } else {
            // Middleware says everything is ok, login
            $key = Security::secureTemporaryKey();
            $this->updateOne(['email' => $email], ['$set' => ['temporary_token' => $key]]);

            if ($user->meta->flags->useMFA) {
                return new LoginResult((string)$user->_id, '2fa');
            }

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
     * Log user in without the full security system
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
     * Check if user has the requested permission by name or object
     *
     * @param  string|\SailCMS\Types\ACL  $permission
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function hasPermission(string|\SailCMS\Types\ACL $permission): bool
    {
        if (is_string($permission)) {
            [$type, $name] = explode('_', $permission);

            $acl = match ($type) {
                'read' => ACL::read($name),
                'readwrite' => ACL::readwrite($name)
            };
        } else {
            $acl = $permission;
        }

        return ACL::hasPermission(self::$currentUser, $acl);
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
     * Remove all users from given group
     *
     * @param  string  $group
     * @return void
     * @throws DatabaseException
     *
     */
    public static function removeGroupFromAll(string $group): void
    {
        $instance = new static();
        $instance->updateMany(['group' => $group], ['$set' => ['group' => '']]);
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
     * Activate MFA for the given user
     *
     * @param  string  $userId
     * @return void
     * @throws DatabaseException
     *
     */
    public static function setMFA(string $userId): void
    {
        $instance = new static();
        $instance->updateOne(['_id' => $instance->ensureObjectId($userId)], ['$set' => ['meta.flags.useMFA' => true]]);
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
     * @throws LoaderError
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
                    2,
                    'reset_password',
                    $record->locale,
                    [
                        'replacements' => [
                            'user_email' => $email,
                        ],
                        'reset_code' => $code,
                        'user_email' => $email,
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
     * @return PasswordChangeResult
     * @throws DatabaseException
     *
     */
    public static function changePassword(string $code, #[SensitiveParameter] string $password): PasswordChangeResult
    {
        $instance = new static();

        $passCheck = true;
        $codeCheck = true;

        // Validate password
        $valid = Security::validatePassword($password);

        if (!$valid) {
            $passCheck = false;
        }

        $user = $instance->findOne(['reset_code' => $code])->exec();

        if (!$user) {
            $codeCheck = false;
        }

        if (!$passCheck || !$codeCheck) {
            return new PasswordChangeResult($passCheck, $codeCheck);
        }

        $instance->updateOne(['reset_code' => $code],
            [
                '$set' => [
                    'password' => Security::hashPassword($password),
                    'reset_code' => ''
                ]
            ]
        );

        return new PasswordChangeResult(true, true);
    }

    /**
     *
     * Change user password for given user id
     *
     * @param  string  $id
     * @param  string  $password
     * @return bool
     * @throws DatabaseException
     *
     */
    public static function changePasswordWithID(string $id, string $password): bool
    {
        // Validate password
        $valid = Security::validatePassword($password);

        if (!$valid) {
            return false;
        }

        self::query()->updateOne(['_id' => self::query()->ensureObjectId($id)],
            [
                '$set' => [
                    'password' => Security::hashPassword($password)
                ]
            ]
        );

        return true;
    }

    /**
     *
     * Get anonymous user
     *
     * @return User
     * @throws DatabaseException
     *
     */
    public static function anonymousUser(): User
    {
        $model = new User();
        $anonymous = $model->getByEmail(self::ANONYMOUS_EMAIL);

        if (!$anonymous) {
            $model->insert([
                'name' => new Username('Anonymous'),
                'avatar' => '',
                'email' => self::ANONYMOUS_EMAIL,
                'roles' => new Collection(['super-administrator']),
                'meta' => new UserMeta((object)['flags' => ['useMFA' => false]]),
                'status' => true,
                'password' => Security::hashPassword(Text::init()->random(16)),
                'locale' => Locale::default(),
                'validated' => true,
                'created_at' => strtotime('01-01-2003')
            ]);
            $anonymous = $model->getByEmail(self::ANONYMOUS_EMAIL);
        }

        return $anonymous;
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
     * Change status of given list of users
     *
     * @param  array|Collection  $ids
     * @param  bool              $status
     * @return bool
     *
     */
    public function changeUserStatus(array|Collection $ids, bool $status): bool
    {
        try {
            $this->hasPermissions();

            // Get Level of current user (cannot enable/disable higher level)
            $currentLevel = Role::getHighestLevel(self::$currentUser->roles);

            if (is_object($ids)) {
                $ids = $ids->unwrap();
            }

            foreach ($ids as $num => $id) {
                $user = $this->findById($id)->project(['roles' => 1])->exec();
                $highest = Role::getHighestLevel($user->roles);

                if ($currentLevel < $highest) {
                    unset($ids[$num]);
                }
            }

            $ids = array_values($ids);
            $idlist = $this->ensureObjectIds($ids);

            $this->updateMany(['_id' => ['$in' => $idlist->unwrap()]], ['$set' => ['status' => $status]]);
            return true;
        } catch (ACLException|DatabaseException|PermissionException $e) {
            return false;
        }
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
        if (Sail::isCLI()) {
            return;
        }

        if (!self::$currentUser) {
            throw new PermissionException('0403: Permission Denied', 0403);
        }

        if ($advanced) {
            if ((string)self::$currentUser->_id !== $id) {
                if ($read) {
                    if (!ACL::hasPermission(self::$currentUser, ACL::read('user'), ACL::write('user'))) {
                        throw new PermissionException('0403: Permission Denied', 0403);
                    }
                } elseif (!ACL::hasPermission(self::$currentUser, ACL::write('user'))) {
                    throw new PermissionException('0403: Permission Denied', 0403);
                }
            }
        } elseif ($read) {
            if (!ACL::hasPermission(self::$currentUser, ACL::read('user'), ACL::write('user'))) {
                throw new PermissionException('0403: Permission Denied', 0403);
            }
        } elseif (!ACL::hasPermission(self::$currentUser, ACL::write('user'))) {
            throw new PermissionException('0403: Permission Denied', 0403);
        }
    }
}