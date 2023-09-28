<?php

namespace SailCMS\GraphQL\Controllers;

use SailCMS\Collection;
use SailCMS\Errors\DatabaseException;
use SailCMS\GraphQL\Context;
use SailCMS\Models\BrokenLink as BrokenLinkModel;
use SailCMS\Types\Listing;

class BrokenLink
{
    /**
     *
     * Get a list of broken links
     *
     * @param mixed $obj
     * @param Collection $args
     * @param Context $context
     * @return Listing
     * @throws DatabaseException
     */
    public function getBrokenLinks(mixed $obj, Collection $args, Context $context): Listing
    {
        return (new BrokenLinkModel())->getList(
            $args->get('page'),
            $args->get('limit'),
            $args->get('search', ''),
            $args->get('sort', 'name'),
            ($args->get('order', 1) === 'DESC') ? -1 : 1
        );
    }
}