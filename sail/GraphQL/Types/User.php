<?php

namespace SailCMS\GraphQL\Types;

use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use SailCMS\Collection;
use SailCMS\Models\Role;
use SailCMS\Errors\DatabaseException;
use SailCMS\GraphQL\Types as GTypes;

class User
{
    public static Collection $roles;

    /**
     *
     * Get User type instance
     *
     * @return Type
     * @throws DatabaseException
     *
     */
    public static function user(): Type
    {
        return new ObjectType([
            'name' => 'User',
            'fields' => [
                '_id' => ['type' => GTypes::ID()],
                'name' => ['type' => Type::nonNull(static::fullname())],
                'email' => ['type' => GTypes::string()],
                'role' => ['type' => GTypes::string()],
                'status' => ['type' => GTypes::boolean()],
                'avatar' => ['type' => GTypes::string()],
                'permissions' => ['type' => Type::listOf(GTypes::string())]
            ]
        ]);
    }

    /**
     *
     * Get UserName type instance
     *
     * @return Type
     *
     */
    public static function fullname(): Type
    {
        return new ObjectType([
            'name' => 'UserName',
            'fields' => [
                'first' => ['type' => GTypes::string()],
                'last' => ['type' => GTypes::string()],
                'full' => ['type' => GTypes::string()]
            ]
        ]);
    }
}