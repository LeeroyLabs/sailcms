<?php

namespace SailCMS\GraphQL\Controllers;

use SailCMS\Collection;
use SailCMS\Errors\DatabaseException;
use SailCMS\GraphQL\Context;
use SailCMS\Models\Redirection as RedirectionModel;
use SailCMS\Types\Listing;

class Redirection
{
    /**
     *
     * Get redirection by id
     *
     * @param mixed $obj
     * @param Collection $args
     * @param Context $context
     * @return RedirectionModel
     * @throws DatabaseException
     */
    public function getRedirection(mixed $obj, Collection $args, Context $context): RedirectionModel
    {
        return (new RedirectionModel())->getById($args->get('id'));
    }

    /**
     *
     * Get a list of redirections
     *
     * @param mixed $obj
     * @param Collection $args
     * @param Context $context
     * @return Listing
     * @throws DatabaseException
     */
    public function getRedirections(mixed $obj, Collection $args, Context $context): Listing
    {
        return (new RedirectionModel())->getList(
            $args->get('page'),
            $args->get('limit'),
            $args->get('search', ''),
            $args->get('sort', 'name'),
            ($args->get('order', 1) === 'DESC') ? -1 : 1
        );
    }

    /**
     *
     * Create a redirection
     *
     * @param mixed $obj
     * @param Collection $args
     * @param Context $context
     * @return bool
     * @throws DatabaseException
     */
    public function createRedirection(mixed $obj, Collection $args, Context $context): bool
    {
        $redirect_type = "302";

        if ($args->get('redirect_type') === "PERMANENT") {
            $redirect_type = "301";
        }

        return RedirectionModel::add(
            $args->get('url'),
            $args->get('redirect_url'),
            $redirect_type,
        );
    }

    /**
     *
     * Update a redirection
     *
     * @param mixed $obj
     * @param Collection $args
     * @param Context $context
     * @return bool
     * @throws DatabaseException
     */
    public function updateRedirection(mixed $obj, Collection $args, Context $context): bool
    {
        return (new RedirectionModel())->update(
            $args->get('id'),
            $args->get('url'),
            $args->get('redirect_url'),
            $args->get('redirect_type'),
        );
    }

    /**
     *
     * Delete a redirection
     *
     * @param mixed $obj
     * @param Collection $args
     * @param Context $context
     * @return bool
     * @throws DatabaseException
     */
    public function deleteRedirection(mixed $obj, Collection $args, Context $context): bool
    {
        return (new RedirectionModel())->delete($args->get('id'));
    }
}