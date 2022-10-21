<?php

namespace SailCMS\GraphQL\Controllers;

use SailCMS\Errors\DatabaseException;
use SailCMS\GraphQL\Context;
use SailCMS\Models\User;

class Users
{
    /**
     *
     * Get a user by id
     *
     * @param  mixed    $obj
     * @param  array    $args
     * @param  Context  $context
     * @return User|null
     * @throws DatabaseException
     *
     */
    public function user(mixed $obj, array $args, Context $context): ?User
    {
        return (new User())->getById($args['id']);
    }
}