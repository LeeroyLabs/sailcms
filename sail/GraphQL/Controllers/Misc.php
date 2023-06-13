<?php

namespace SailCMS\GraphQL\Controllers;

use SailCMS\Collection;
use SailCMS\GraphQL\Context;
use SailCMS\UI;

class Misc
{
    /**
     *
     * Get navigation for use on the frontend
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return Collection
     *
     */
    public function navigationElements(mixed $obj, Collection $args, Context $context): Collection
    {
        return UI::getNavigationElements();
    }
}