<?php

namespace SailCMS\GraphQL\Controllers;

use SailCMS\Collection;
use SailCMS\Errors\DatabaseException;
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
            return $nav->structure->get();
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

    // edit
    // delete
}