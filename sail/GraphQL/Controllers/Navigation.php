<?php

namespace SailCMS\GraphQL\Controllers;

use SailCMS\Collection;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\EntryException;
use SailCMS\Errors\NavigationException;
use SailCMS\Errors\PermissionException;
use SailCMS\GraphQL\Context;
use \SailCMS\Models\Navigation as NavigationModel;

class Navigation
{
    /**
     *
     * Get navigation for use on the frontend
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return array|null
     * @throws DatabaseException
     *
     */
    public function navigation(mixed $obj, Collection $args, Context $context): ?array
    {
        $nav = NavigationModel::getByName($args->get('name'));

        if ($nav) {
            return $nav->structure->castFrom();
        }

        return [];
    }

    /**
     *
     * Get navigation for editing
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return NavigationModel|null
     * @throws DatabaseException
     *
     */
    public function navigationDetails(mixed $obj, Collection $args, Context $context): ?NavigationModel
    {
        return NavigationModel::getByName($args->get('name'));
    }

    /**
     *
     * Create a navigation and return the name
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return string
     * @throws DatabaseException
     * @throws ACLException
     * @throws NavigationException
     * @throws PermissionException
     * @throws EntryException
     *
     */
    public function createNavigation(mixed $obj, Collection $args, Context $context): string
    {
        return (new NavigationModel())->create(
            $args->get('name'),
            $args->get('structure'),
            $args->get('locale', 'en'),
            $args->get('site_id', 'default')
        );
    }

    /**
     *
     * Update a navigation by its name
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws NavigationException
     * @throws PermissionException
     *
     */
    public function updateNavigation(mixed $obj, Collection $args, Context $context): bool
    {
        return (new NavigationModel())->update(
            $args->get('name'),
            $args->get('structure'),
            $args->get('locale', 'en')
        );
    }

    /**
     *
     * Delete navigation by name
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return bool
     * @throws DatabaseException
     *
     */
    public function deleteNavigation(mixed $obj, Collection $args, Context $context): bool
    {
        return (new NavigationModel())->delete($args->get('id'));
    }
}