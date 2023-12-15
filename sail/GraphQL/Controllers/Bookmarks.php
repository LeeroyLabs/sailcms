<?php

namespace SailCMS\GraphQL\Controllers;

use SailCMS\Collection;
use SailCMS\Errors\DatabaseException;
use SailCMS\GraphQL\Context;
use SailCMS\Models\Bookmark;
use SailCMS\Types\LocaleField;

class Bookmarks
{
    /**
     *
     * Get user bookmarks
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return bool
     * @throws DatabaseException
     *
     */
    public function addBookmark(mixed $obj, Collection $args, Context $context): bool
    {
        $bookmark = new Bookmark();

        $localeName = new LocaleField($args->get('name'));
        return $bookmark->add($args->get('user_id'), $args->get('url'), $localeName);
    }

    /**
     *
     * Delete a bookmark
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return bool
     * @throws DatabaseException
     *
     */
    public function removeBookmark(mixed $obj, Collection $args, Context $context): bool
    {
        return (new Bookmark())->delete($args->get('user_id'), $args->get('url'));
    }
}