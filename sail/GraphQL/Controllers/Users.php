<?php

namespace SailCMS\GraphQL\Controllers;

use GraphQL\Type\Definition\ResolveInfo;
use League\Flysystem\FilesystemException;
use SailCMS\Collection;
use SailCMS\Database\BaseModel;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\GraphQL\Context;
use SailCMS\Models\Tfa;
use SailCMS\Models\User;
use SailCMS\Security\TwoFactorAuthentication;
use SailCMS\Types\Listing;
use SailCMS\Types\UserMeta;
use SailCMS\Types\Username;
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
     * List users
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return Listing
     * @throws ACLException
     * @throws DatabaseException
     *
     */
    public function users(mixed $obj, Collection $args, Context $context): Listing
    {
        $order = BaseModel::SORT_ASC;

        if ($args->get('order') !== null && strtolower($args->get('order')) !== 'asc') {
            $order = BaseModel::SORT_DESC;
        }

        return (new User())->getList(
            $args->get('page'),
            $args->get('limit'),
            $args->get('search') ?? '',
            $args->get('sort') ?? 'name.first',
            $order
        );
    }

    /**
     *
     * Verify given credentials and return appropriate response (logged in, 2fa or error)
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return string
     * @throws DatabaseException
     *
     */
    public function authenticate(mixed $obj, Collection $args, Context $context): string
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
     * @throws DatabaseException
     *
     */
    public function createUser(mixed $obj, Collection $args, Context $context): bool
    {
        $user = new User();

        $name = Username::initWith($args->get('name'));
        $meta = ($args->get('meta')) ? new UserMeta($args->get('meta')) : null;
        $id = $user->createRegularUser(
            $name,
            $args->get('email'),
            $args->get('password'),
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
            $args->get('password'),
            $args->get('roles', []),
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