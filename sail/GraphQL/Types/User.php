<?php

namespace SailCMS\GraphQL\Types;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use SailCMS\Collection;
use SailCMS\GraphQL\Types as GTypes;
use GraphQL\Type\Definition\ResolveInfo;
use SailCMS\Models\User as UserModel;
use SailCMS\Types\UserMeta;

class User
{
    public static Collection $roles;

    /**
     *
     * Get User type instance
     *
     * @return Type
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
                'roles' => ['type' => Type::listOf(GTypes::string())],
                'status' => ['type' => GTypes::boolean()],
                'avatar' => ['type' => GTypes::string()],
                'permissions' => ['type' => Type::listOf(GTypes::string())],
                'meta' => ['type' => Type::nonNull(static::meta())]
            ],
            'resolveField' => function (UserModel $user, array $args, $context, ResolveInfo $info)
            {
                if ($info->fieldName === 'permissions') {
                    return $user->permissions();
                }

                return $user->{$info->fieldName};
            }
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

    public static function meta(): Type
    {
        // Fetch Available flags from the User model
        $metas = UserMeta::getAvailableMeta();
        $subTypes = [];

        $metas->each(function ($key, $value) use (&$subTypes)
        {
            switch ($value->get('type')) {
                default:
                case UserMeta::TYPE_STRING:
                    $type = GTypes::string(true);
                    $subTypes[$key] = ['type' => $type];
                    break;

                case UserMeta::TYPE_INT:
                    $type = GTypes::int(true);
                    $subTypes[$key] = ['type' => $type];
                    break;

                case UserMeta::TYPE_FLOAT:
                    $type = GTypes::float(true);
                    $subTypes[$key] = ['type' => $type];
                    break;

                case UserMeta::TYPE_BOOL:
                    $type = GTypes::boolean(true);
                    $subTypes[$key] = ['type' => $type];
                    break;

                case UserMeta::TYPE_CUSTOM:
                    if ($value->get('callback')) {
                        $types = call_user_func($value->get('callback')->unwrap());
                        $subTypes[$key] = new ObjectType(['name' => $key, 'fields' => $types->unwrap()]);
                    }
                    break;
            }
        });

        return new ObjectType([
            'name' => 'UserMeta',
            'fields' => $subTypes
        ]);
    }
}