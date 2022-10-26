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
     * @return ObjectType
     *
     */
    public static function user(): ObjectType
    {
        return new ObjectType([
            'name' => 'User',
            'fields' => [
                '_id' => GTypes::ID(),
                'name' => Type::nonNull(static::fullname()),
                'email' => GTypes::string(),
                'roles' => Type::listOf(GTypes::string()),
                'status' => GTypes::boolean(),
                'avatar' => GTypes::string(),
                'permissions' => Type::listOf(GTypes::string()),
                'meta' => Type::nonNull(static::meta())
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
     * List of users
     *
     * @return ObjectType
     *
     */
    public static function listing(): ObjectType
    {
        $user = static::user();

        return new ObjectType([
            'name' => 'UserListing',
            'fields' => [
                'pagination' => General::pagination(),
                'list' => Type::listOf(Type::nonNull($user))
            ]
        ]);
    }

    /**
     *
     * Get UserName type instance
     *
     * @return ObjectType
     */
    public static function fullname(): ObjectType
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
     * Meta data for a user
     *
     * @return ObjectType
     *
     */
    public static function meta(): ObjectType
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