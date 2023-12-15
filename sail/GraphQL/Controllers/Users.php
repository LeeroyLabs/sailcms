<?php

namespace SailCMS\GraphQL\Controllers;

use GraphQL\Type\Definition\ResolveInfo;
use League\Flysystem\FilesystemException;
use SailCMS\Collection;
use SailCMS\Database\Model;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\EmailException;
use SailCMS\Errors\FileException;
use SailCMS\Errors\PermissionException;
use SailCMS\GraphQL\Context;
use SailCMS\Middleware;
use SailCMS\Models\Bookmark;
use SailCMS\Models\Role;
use SailCMS\Models\Tfa;
use SailCMS\Models\User;
use SailCMS\Security\TwoFactorAuthentication;
use SailCMS\Types\Listing;
use SailCMS\Types\LoginResult;
use SailCMS\Types\MetaSearch;
use SailCMS\Types\MiddlewareType;
use SailCMS\Types\PasswordChangeResult;
use SailCMS\Types\UserMeta;
use SailCMS\Types\Username;
use SailCMS\Types\UserSorting;
use SailCMS\Types\UserTypeSearch;
use SodiumException;
use stdClass;
use Twig\Error\LoaderError;

class Users
{
    /**
     *
     * Get a user by id
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return User|null
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function user(mixed $obj, Collection $args, Context $context): ?User
    {
        $user = (new User())->getById($args->get('id'));

        if ($user) {
            $user->meta = $user->meta->simplify();
        }

        return $user;
    }

    /**
     *
     * Return the user (if authentication has succeeded)
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return User|null
     *
     */
    public function userWithToken(mixed $obj, Collection $args, Context $context): ?User
    {
        $user = User::$currentUser ?? null;

        if ($user) {
            $user->meta = $user->meta->simplify();
        }

        return $user;
    }

    /**
     *
     * Resend a user' validation email
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return bool
     * @throws DatabaseException
     *
     */
    public function resendValidationEmail(mixed $obj, Collection $args, Context $context): bool
    {
        return (new User())->resendValidationEmail($args->get('email'));
    }

    /**
     *
     * List users
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return Listing
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function users(mixed $obj, Collection $args, Context $context): Listing
    {
        $order = Model::SORT_ASC;

        if ($args->get('order') !== null && strtolower($args->get('order')) !== 'asc') {
            $order = Model::SORT_DESC;
        }

        $meta = $args->get('meta', '');
        $userType = $args->get('type', '');
        $sorting = $args->get('sorting');

        if ($meta) {
            $metaSearch = new MetaSearch($meta->get('key'), $meta->get('value'));
        }

        if ($userType !== '') {
            $userTypeSearch = new UserTypeSearch($userType->get('type'), $userType->get('except'));
        }

        if ($sorting) {
            $sorting = new UserSorting($sorting->get('sort'), $sorting->get('order'));
        } else {
            $sorting = new UserSorting('name.full', 'asc');
        }

        $list = (new User())->getList(
            $args->get('page'),
            $args->get('limit'),
            $args->get('search') ?? '',
            $sorting,
            $userTypeSearch ?? null,
            $metaSearch ?? null,
            $args->get('status'),
            $args->get('validated'),
            $args->get('group_id', '')
        );

        $list->list->each(function ($key, $value)
        {
            $value->meta = $value->meta->simplify();
        });

        return $list;
    }

    /**
     *
     * Verify given credentials and return appropriate response (logged in, 2fa or error)
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return LoginResult
     * @throws DatabaseException
     *
     */
    public function authenticate(mixed $obj, Collection $args, Context $context): LoginResult
    {
        return (new User())->verifyUserPass($args->get('email'), $args->get('password'));
    }

    /**
     *
     * Verify temporary token for login
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return User|null
     * @throws DatabaseException
     *
     */
    public function verifyAuthenticationToken(mixed $obj, Collection $args, Context $context): ?User
    {
        $user = (new User())->verifyTemporaryToken($args->get('token'));

        if ($user) {
            $user->meta = $user->meta->simplify();
        }

        return $user;
    }

    /**
     *
     * Validate a 2FA code for given user
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return User|null
     * @throws DatabaseException
     * @throws FilesystemException
     * @throws SodiumException
     *
     */
    public function verifyTFA(mixed $obj, Collection $args, Context $context): ?User
    {
        $model = new Tfa();
        $tfa = new TwoFactorAuthentication();
        $setup = $model->getForUser($args->get('user_id'));

        if ($setup) {
            $result = $tfa->validate($setup->secret, $args->get('code'));

            if ($result) {
                $user = User::get($args->get('user_id'));

                if ($user) {
                    return (new User())->verifyTemporaryToken($user->temporary_token);
                }
            }
        }

        return null;
    }

    /**
     *
     * Create a new user
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return string
     * @throws DatabaseException
     *
     */
    public function createUser(mixed $obj, Collection $args, Context $context): string
    {
        return $this->createUserShared($args);
    }

    /**
     *
     * Create a user
     *
     * @param  Collection  $args
     * @return string
     * @throws DatabaseException
     *
     */
    private function createUserShared(Collection $args): string
    {
        $user = new User();
        $name = Username::initWith($args->get('name'));
        $meta = ($args->get('meta')) ? new UserMeta($args->get('meta', Collection::init())) : null;

        return $user->createRegularUser(
            $name,
            $args->get('email'),
            $args->get('password'),
            $args->get('locale', 'en'),
            $args->get('avatar', ''),
            $meta,
            $args->get('roles', []),
            $args->get('group', ''),
            $args->get('createWithSetPassword', false),
            $args->get('useEmailTemplate', '')
        );
    }

    /**
     *
     * Create an admin user
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return string
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function createAdminUser(mixed $obj, Collection $args, Context $context): string
    {
        $user = new User();

        $name = Username::initWith($args->get('name'));
        $meta = ($args->get('meta')) ? new UserMeta($args->get('meta')) : null;
        return $user->create(
            $name,
            $args->get('email'),
            '', // no password for admins
            $args->get('roles', []),
            $args->get('locale', 'en'),
            $args->get('avatar', ''),
            $meta
        );
    }

    /**
     *
     * Update a user
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function updateUser(mixed $obj, Collection $args, Context $context): bool
    {
        $user = new User();
        $roles = $args->get('roles');
        $meta = $args->get('meta');
        $name = $args->get('name');

        if ($roles && is_array($roles)) {
            $roles = new Collection($roles);
        }

        if ($meta) {
            $meta = new UserMeta((object)$meta);
        }

        if ($name) {
            $name = Username::initWith((object)$name);
        }

        return $user->update(
            $args->get('id'),
            $name,
            $args->get('email'),
            $args->get('password'),
            $roles,
            $args->get('avatar'),
            $meta,
            $args->get('locale')
        );
    }

    /**
     *
     * Delete a user by his id
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function deleteUser(mixed $obj, Collection $args, Context $context): bool
    {
        return (new User())->removeById($args->get('id'));
    }

    /**
     *
     * Delete users from given list
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function deleteUsers(mixed $obj, Collection $args, Context $context): bool
    {
        return (new User())->removeByIdList($args->get('ids'));
    }

    /**
     *
     * Validate an account with given code
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return bool
     * @throws DatabaseException
     *
     */
    public function validateAccount(mixed $obj, Collection $args, Context $context): bool
    {
        return User::validateWithCode($args->get('code', 'invalid-code'));
    }

    /**
     *
     * Forgot password, send an email for the request
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return bool
     * @throws DatabaseException
     * @throws EmailException
     * @throws FileException
     * @throws LoaderError
     *
     */
    public function forgotPassword(mixed $obj, Collection $args, Context $context): bool
    {
        return User::forgotPassword($args->get('email', ''));
    }

    /**
     *
     * Change the password of the given code's user
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return PasswordChangeResult
     * @throws DatabaseException
     *
     */
    public function changePassword(mixed $obj, Collection $args, Context $context): PasswordChangeResult
    {
        return User::changePassword($args->get('code', ''), $args->get('password', ''));
    }

    /**
     *
     * Change status of given users (only users with lower roles will actually change)
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return bool
     *
     */
    public function changeUserStatus(mixed $obj, Collection $args, Context $context): bool
    {
        return (new User())->changeUserStatus($args->get('ids'), $args->get('status'));
    }

    /**
     *
     * Resolve custom fields
     *
     * @param  mixed        $obj
     * @param  Collection   $args
     * @param  Context      $context
     * @param  ResolveInfo  $info
     * @return mixed
     * @throws DatabaseException
     *
     */
    public function resolver(mixed $obj, Collection $args, Context $context, ResolveInfo $info): mixed
    {
        // This fixes the "expecting String but got instance of"
        if ($info->fieldName === 'meta') {
            // Ask Middleware
            $data = new Middleware\Data(Middleware\Login::Meta, $obj);
            $obj = Middleware::execute(MiddlewareType::LOGIN, $data)->data;

            if (is_object($obj->meta) && get_class($obj->meta) === stdClass::class) {
                $meta = new UserMeta();
                return $meta->castTo($obj->meta)->castFrom();
            }

            if (is_object($obj->meta) && get_class($obj->meta) === UserMeta::class) {
                return $obj->meta->castFrom();
            }

            return $obj->meta;
        }

        if ($info->fieldName === 'bookmarks') {
            $bm = new Bookmark();
            $obj->bookmarks = $bm->getList($obj->id);
        }

        if (($info->fieldName === 'group') && !isset($obj->group)) {
            return '';
        }

        if ($info->fieldName === 'permissions') {
            return $obj->permissions();
        }

        if ($info->fieldName === 'highest_level') {
            if ($obj->roles->length > 0) {
                return Role::getHighestLevel($obj->roles);
            }

            return 0;
        }

        return $obj->{$info->fieldName};
    }
}