<?php

namespace SailCMS\GraphQL\Controllers;

use GraphQL\Type\Definition\ResolveInfo;
use SailCMS\Database\BaseModel;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\GraphQL\Context;
use SailCMS\Models\User;
use SailCMS\Types\Listing;

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

    /**
     *
     * List users
     *
     * @param  mixed    $obj
     * @param  array    $args
     * @param  Context  $context
     * @return Listing
     * @throws DatabaseException
     * @throws ACLException
     *
     */
    public function users(mixed $obj, array $args, Context $context): Listing
    {
        $order = BaseModel::SORT_ASC;
        
        if (isset($args['order']) && strtolower($args['order']) !== 'asc') {
            $order = BaseModel::SORT_DESC;
        }

        return (new User())->getList(
            $args['page'],
            $args['limit'],
            $args['search'] ?? '',
            $args['sort'] ?? 'name.first',
            $order
        );
    }

    /**
     *
     * Resolve custom fields
     *
     * @param  mixed        $obj
     * @param  array        $args
     * @param  Context      $context
     * @param  ResolveInfo  $info
     * @return mixed
     *
     */
    public function resolver(mixed $obj, array $args, Context $context, ResolveInfo $info): mixed
    {
        if ($info->fieldName === 'name') {
            return $obj->name->toDBObject();
        }

        if ($info->fieldName === 'permissions') {
            return $obj->permissions()->unwrap();
        }

        if ($info->fieldName === 'roles') {
            return $obj->roles->unwrap();
        }

        return $obj->{$info->fieldName};
    }
}