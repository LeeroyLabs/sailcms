<?php

namespace SailCMS\GraphQL\Controllers;

use GraphQL\Type\Definition\ResolveInfo;
use RuntimeException;
use SailCMS\ACL;
use SailCMS\Collection;
use SailCMS\Debug;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\PermissionException;
use SailCMS\GraphQL\Context;
use SailCMS\Models\Role;
use SailCMS\Models\User;
use SailCMS\Text;
use SailCMS\Types\RoleConfig;

class Roles
{
    /**
     *
     * Get a role by id
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return RoleConfig|null
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function role(mixed $obj, Collection $args, Context $context): ?RoleConfig
    {
        return (new Role())->getById($args->get('id'));
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
     * @throws PermissionException
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
     * @throws PermissionException
     *
     */
    public function createRole(mixed $obj, Collection $args, Context $context): bool
    {
        return (new Role())->create(
            $args->get('name'),
            $args->get('description'),
            $args->get('permissions', []),
            $args->get('level', 100)
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
     * @throws PermissionException
     *
     */
    public function updateRole(mixed $obj, Collection $args, Context $context): bool
    {
        return (new Role())->update(
            $args->get('id'),
            $args->get('name', ''),
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
     * @throws PermissionException
     *
     */
    public function delete(mixed $obj, Collection $args, Context $context): bool
    {
        return (new Role())->delete($args->get('id'));
    }

    /**
     *
     * Permissions
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return Collection
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function permissions(mixed $obj, Collection $args, Context $context): Collection
    {
        if (ACL::hasPermission(User::$currentUser, ACL::read('role'))) {
            $data = ACL::getCompleteList();

            foreach ($data as $item) {
                $simple = $item->description->castFrom();
                $cat = $item->category;

                if ($simple['fr'] === '' && $simple['en'] === '') {
                    switch ($cat) {
                        case 'RW':
                            $item->description = [
                                'fr' => 'Gestion des ' . Text::from($item->givenName)->pluralize()->value(),
                                'en' => 'Manage ' . Text::from($item->givenName)->pluralize()->value()
                            ];
                            break;

                        case 'R':
                            $item->description = [
                                'fr' => 'Accès lecture des ' . Text::from($item->givenName)->pluralize()->value(),
                                'en' => 'Read access to ' . Text::from($item->givenName)->pluralize()->value()
                            ];
                            break;

                        case 'W':
                            $item->description = [
                                'fr' => 'Accès écriture des ' . Text::from($item->givenName)->pluralize()->value(),
                                'en' => 'Write access to ' . Text::from($item->givenName)->pluralize()->value()
                            ];
                            break;
                    }
                } else {
                    $item->description = $item->description->castFrom();
                }
            }

            return $data;
        }

        return Collection::init();
    }

    /**
     *
     * Role resolver
     *
     * @param  mixed        $obj
     * @param  Collection   $args
     * @param  Context      $context
     * @param  ResolveInfo  $info
     * @return mixed
     *
     */
    public function resolver(mixed $obj, Collection $args, Context $context, ResolveInfo $info): mixed
    {
        if ($info->fieldName === 'member_count') {
            return User::countForRole($obj->slug);
        }

        return $obj->{$info->fieldName};
    }

    /**
     *
     * Permission resolver
     *
     * @param  mixed        $obj
     * @param  Collection   $args
     * @param  Context      $context
     * @param  ResolveInfo  $info
     * @return mixed
     *
     */
    public function permissionResolver(mixed $obj, Collection $args, Context $context, ResolveInfo $info): mixed
    {
        switch ($info->fieldName) {
            case 'name':
                return $obj->name;

            case 'unnormalized_name':
                if ($obj->givenName === 'Register' || $obj->givenName === 'Navigation') {
                    return Text::from($obj->givenName)->capitalize()->value();
                }

                return Text::from($obj->givenName)->pluralize()->capitalize()->value();

            case 'group':
                return $obj->group;

            case 'category':
                return $obj->category;
        }

        return $obj->{$info->fieldName};
    }
}