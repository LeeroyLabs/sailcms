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
use SodiumException;

/*
 * Implementation list
 *
 * - user
 * - users
 * createUser
 * - updateUser
 * - deleteUser
 * - authenticate
 * - verifyAuthenticationToken
 * verifyTFA
 *
 */

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

    public function createUser(mixed $obj, Collection $args, Context $context): bool
    {
        return false;
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