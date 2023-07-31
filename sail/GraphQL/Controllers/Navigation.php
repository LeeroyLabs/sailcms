<?php

namespace SailCMS\GraphQL\Controllers;

use RuntimeException;
use SailCMS\Collection;
use SailCMS\Database\Model;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\EntryException;
use SailCMS\Errors\NavigationException;
use SailCMS\Errors\PermissionException;
use SailCMS\GraphQL\Context;
use SailCMS\Models\Navigation as NavigationModel;
use SailCMS\Sail;

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
        $nav = NavigationModel::getBySlug($args->get('slug'));

        if ($nav) {
            return $nav->structure->castFrom();
        }

        return [];
    }

    /**
     *
     * Get a list on navigation details
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return array|null
     * @throws DatabaseException
     *
     */
    public function navigationDetailsList(mixed $obj, Collection $args, Context $context): ?array
    {
        $sort = $args->get('sort', 'title');
        $direction = strtolower($args->get('direction', 'ASC'));
        $locale = $args->get('locale');
        $siteId = $args->get('site_id');

        $direction = match ($direction) {
            'desc' => Model::SORT_DESC,
            default => Model::SORT_ASC
        };

        return NavigationModel::getList($sort, $direction, $locale, $siteId);
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
        return NavigationModel::getBySlug($args->get('slug'));
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
            $args->get('title'),
            $args->get('structure'),
            $args->get('locale', 'en'),
            $args->get('site_id', Sail::siteId())
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
        if ($args->get('slug')) {
            return (new NavigationModel())->update(
                $args->get('id'),
                $args->get('title'),
                $args->get('slug'),
                $args->get('structure'),
                $args->get('locale', 'en')
            );
        }

        throw new RuntimeException('Please enter a slug');
    }

    /**
     *
     * Delete navigation by name
     *
     * @param mixed $obj
     * @param Collection $args
     * @param Context $context
     * @return bool
     *
     * @throws DatabaseException
     */
    public function deleteNavigation(mixed $obj, Collection $args, Context $context): bool
    {
        return (new NavigationModel())->delete($args->get('id'));
    }
}