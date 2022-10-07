<?php

namespace SailCMS\Containers\Users\GraphQL;

use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use SailCMS\Collection;
use SailCMS\Models\Role;
use SailCMS\Errors\DatabaseException;
use \SailCMS\GraphQL\Types as GTypes;

class Types
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
                'role' => ['type' => Type::nonNull(static::userType())],
                'status' => ['type' => GTypes::boolean()],
                'avatar' => ['type' => GTypes::string()],
                'test' => ['type' => Type::listOf(Gtypes::string())]
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

    /**
     *
     * Generate the UserType type instance
     *
     * @return Type
     * @throws DatabaseException
     *
     */
    public static function userType(): Type
    {
        if (!isset(static::$roles)) {
            static::$roles = Role::getAll();
        }

        $values = [
            'USER' => ['value' => 'user'],
            'AUTHOR' => ['value' => 'author'],
            'ADMIN' => ['value' => 'admin'],
        ];

        foreach (static::$roles->unwrap() as $role) {
            $values[strtoupper($role->slug)] = ['value' => $role->slug];
        }

        return new EnumType([
            'name' => 'UserType',
            'values' => $values
        ]);
    }
}