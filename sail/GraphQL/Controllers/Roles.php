<?php

namespace SailCMS\GraphQL\Controllers;

use RuntimeException;
use SailCMS\ACL;
use SailCMS\Collection;
use SailCMS\Debug;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\PermissionException;
use SailCMS\GraphQL\Context;
use SailCMS\Models\Role;
use SailCMS\Models\User;
use SailCMS\Types\RoleConfig;

class Roles
{
    /**
     *
     * Get a role by id
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return RoleConfig|null
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function role(mixed $obj, Collection $args, Context $context): ?RoleConfig
    {
        return (new Role())->getById($args->get('id'));
    }

    /**
     *
     * List of roles (only shows the ones user is a part of)
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return Collection
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function roles(mixed $obj, Collection $args, Context $context): Collection
    {
        return (new Role())->list();
    }

    /**
     *
     * Get list of available ACLs
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return Collection
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function acls(mixed $obj, Collection $args, Context $context): Collection
    {
        if (ACL::hasPermission(User::$currentUser, ACL::readwrite('role'))) {
            return ACL::getList();
        }

        return Collection::init();
    }

    /**
     *
     * Create a role
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws RuntimeException
     * @throws PermissionException
     *
     */
    public function create(mixed $obj, Collection $args, Context $context): bool
    {
        return (new Role())->create(
            $args->get('name'),
            $args->get('description'),
            $args->get('permissions')
        );
    }

    /**
     *
     * Update a role
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
    public function update(mixed $obj, Collection $args, Context $context): bool
    {
        return (new Role())->update(
            $args->get('id'),
            $args->get('name' . ''),
            $args->get('level', -1),
            $args->get('description', ''),
            $args->get('permissions', [])
        );
    }

    /**
     *
     * Delete a role
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
    public function delete(mixed $obj, Collection $args, Context $context): bool
    {
        return (new Role())->remove($args->get('id'));
    }
}