<?php

namespace SailCMS\GraphQL\Controllers;

use RuntimeException;
use SailCMS\Collection;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\GraphQL\Context;
use SailCMS\Models\Role;

/**
 *
 * Implementation list
 *
 * single
 *
 */
class Roles
{
    public function role(mixed $obj, Collection $args, Context $context): ?Role
    {
        return null;
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
     *
     */
    public function roles(mixed $obj, Collection $args, Context $context): Collection
    {
        return (new Role())->list();
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
     *
     */
    public function delete(mixed $obj, Collection $args, Context $context): bool
    {
        return (new Role())->remove($args->get('id'));
    }
}