<?php

namespace SailCMS\GraphQL\Controllers;

use SailCMS\ACL;
use SailCMS\Collection;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\PermissionException;
use SailCMS\GraphQL\Context;
use SailCMS\Models\User;
use SailCMS\Register;

class Registers
{
    /**
     *
     * Get the registered containers and modules
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return object
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function registeredExtensions(mixed $obj, Collection $args, Context $context): object
    {
        if (ACL::hasPermission(User::$currentUser, ACL::read('register'))) {
            return (object)[
                'containers' => Register::getContainerList(),
                'modules' => Register::getModules()
            ];
        }

        return (object)[
            'containers' => [],
            'modules' => []
        ];
    }
}