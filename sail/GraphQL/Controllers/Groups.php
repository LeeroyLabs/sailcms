<?php

namespace SailCMS\GraphQL\Controllers;

use GraphQL\Type\Definition\ResolveInfo;
use SailCMS\Collection;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\PermissionException;
use SailCMS\GraphQL\Context;
use SailCMS\Models\User;
use SailCMS\Models\UserGroup;

class Groups
{
    /**
     *
     * Get a single group
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return UserGroup|null
     * @throws DatabaseException
     *
     */
    public function group(mixed $obj, Collection $args, Context $context): ?UserGroup
    {
        return UserGroup::get($args->get('id'));
    }

    /**
     *
     * Get all groups
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return Collection
     *
     */
    public function groups(mixed $obj, Collection $args, Context $context): Collection
    {
        return UserGroup::getAll();
    }

    /**
     *
     * Create a group
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return bool
     * @throws DatabaseException
     * @throws ACLException
     * @throws PermissionException
     *
     */
    public function createGroup(mixed $obj, Collection $args, Context $context): bool
    {
        $id = UserGroup::create($args->get('name'));
        return ($id !== '');
    }

    /**
     *
     * Update a group
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
    public function updateGroup(mixed $obj, Collection $args, Context $context): bool
    {
        return UserGroup::update($args->get('id'), $args->get('name'));
    }

    /**
     *
     * Delete a group
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
    public function deleteGroup(mixed $obj, Collection $args, Context $context): bool
    {
        return UserGroup::delete($args->get('id'));
    }

    /**
     *
     * Type Resolver
     *
     * @param  mixed        $obj
     * @param  Collection   $args
     * @param  Context      $context
     * @param  ResolveInfo  $info
     * @return mixed
     *
     */
    public function groupResolver(mixed $obj, Collection $args, Context $context, ResolveInfo $info): mixed
    {
        if ($info->fieldName === 'user_count') {
            return User::countForGroup((string)$obj->_id);
        }

        return $obj->{$info->fieldName};
    }
}