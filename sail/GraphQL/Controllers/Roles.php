<?php

namespace SailCMS\GraphQL\Controllers;

use SailCMS\Collection;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\GraphQL\Context;
use SailCMS\Models\User;

class Roles
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
}