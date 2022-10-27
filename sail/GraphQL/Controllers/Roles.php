<?php

namespace SailCMS\GraphQL\Controllers;

use SailCMS\Collection;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\GraphQL\Context;
use SailCMS\Models\Role;
use SailCMS\Models\User;

/**
 *
 * Implementation list
 *
 * all
 * update
 * create
 * delete
 *
 */
class Roles
{
    public function single(mixed $obj, Collection $args, Context $context): ?Role
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
    public function all(mixed $obj, Collection $args, Context $context): Collection
    {
        return (new Role())->list();
    }

    public function create(mixed $obj, Collection $args, Context $context): bool
    {
        return true;
    }

    public function update(mixed $obj, Collection $args, Context $context): bool
    {
        return true;
    }

    public function delete(mixed $obj, Collection $args, Context $context): bool
    {
        return true;
    }

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
}