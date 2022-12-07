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
use SailCMS\Models\Tfa;
use SailCMS\Models\User;
use SailCMS\Security\TwoFactorAuthentication;
use SailCMS\Types\Listing;
use SailCMS\Types\LoginResult;
use SailCMS\Types\MetaSearch;
use SailCMS\Types\UserMeta;
use SailCMS\Types\Username;
use SailCMS\Types\UserTypeSearch;
use SodiumException;

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
        return (new User())->getById($args->get('id'));
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
        return User::$currentUser ?? null;
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
        $metaValue = $args->get('meta_value', '');
        $userType = $args->get('user_type', '');
        $exceptType = $args->get('except_type', false);

        if ($meta !== '' && $metaValue !== '') {
            $metaSearch = new MetaSearch($meta, $metaValue);
        }

        if ($userType !== '') {
            $userTypeSearch = new UserTypeSearch($userType, $exceptType);
        }

        return (new User())->getList(
            $args->get('page'),
            $args->get('limit'),
            $args->get('search') ?? '',
            $args->get('sort') ?? 'name.first',
            $order,
            $userTypeSearch ?? null,
            $metaSearch ?? null,
            $args->get('status', null)
        );
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
        return (new User())->verifyTemporaryToken($args->get('token'));
    }

    /**
     *
     * Validate a 2FA code for given user
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return bool
     * @throws DatabaseException
     * @throws FilesystemException
     * @throws SodiumException
     *
     */
    public function verifyTFA(mixed $obj, Collection $args, Context $context): bool
    {
        $model = new Tfa();
        $tfa = new TwoFactorAuthentication();
        $setup = $model->getForUser($args->get('user_id'));

        if ($setup) {
            return $tfa->validate($setup->secret, $args->get('code'));
        }

        return false;
    }

    /**
     *
     * Create a new user
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
    public function createUser(mixed $obj, Collection $args, Context $context): bool
    {
        $user = new User();
        $name = Username::initWith($args->get('name'));
        $meta = ($args->get('meta')) ? new UserMeta($args->get('meta', Collection::init())) : null;

        $id = $user->createRegularUser(
            $name,
            $args->get('email'),
            $args->get('password'),
            $args->get('locale', 'en'),
            $args->get('avatar', ''),
            $meta
        );

        return (!empty($id));
    }

    /**
     *
     * Create an admin user
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
    public function createAdminUser(mixed $obj, Collection $args, Context $context): bool
    {
        $user = new User();

        $name = Username::initWith($args->get('name'));
        $meta = ($args->get('meta')) ? new UserMeta($args->get('meta')) : null;
        $id = $user->create(
            $name,
            $args->get('email'),
            '', // no password for admins
            $args->get('roles', []),
            $args->get('locale', 'en'),
            $args->get('avatar', ''),
            $meta
        );

        return (!empty($id));
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
        $roles = $args->get('roles', null);
        $meta = $args->get('meta', null);
        $name = $args->get('name', null);

        if ($roles) {
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
            $args->get('email', null),
            $args->get('password', null),
            $roles,
            $args->get('avatar', null),
            $meta
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
     * @param  mixed        $obj
     * @param  Collection   $args
     * @param  Context      $context
     * @param  ResolveInfo  $info
     * @return bool
     * @throws DatabaseException
     * @throws FileException
     * @throws EmailException
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
     * @param  mixed        $obj
     * @param  Collection   $args
     * @param  Context      $context
     * @param  ResolveInfo  $info
     * @return mixed
     * @throws DatabaseException
     *
     */
    public function changePassword(mixed $obj, Collection $args, Context $context): mixed
    {
        return User::changePassword($args->get('code', ''), $args->get('password', ''));
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
     *
     */
    public function resolver(mixed $obj, Collection $args, Context $context, ResolveInfo $info): mixed
    {
        if ($info->fieldName === 'name') {
            return $obj->name->toDBObject();
        }

        if ($info->fieldName === 'permissions') {
            return $obj->permissions()->unwrap();
        }

        if ($info->fieldName === 'roles') {
            return $obj->roles->unwrap();
        }

        return $obj->{$info->fieldName};
    }
}