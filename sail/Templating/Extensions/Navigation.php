<?php

namespace SailCMS\Templating\Extensions;

use SailCMS\Errors\DatabaseException;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use \SailCMS\Models\Navigation as NavModel;

class Navigation extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('nav', [$this, 'navigation']),
            new TwigFunction('navigation', [$this, 'navigation']),
        ];
    }

    public function getFilters(): array
    {
        return [];
    }

    /**
     *
     * Get a navigation by name
     *
     * @param  string  $name
     * @return array
     * @throws DatabaseException
     *
     */
    public function navigation(string $name): array
    {
        $nav = NavModel::getByName($name);

        if ($nav) {
            return $nav->structure->get();
        }

        return [];
    }
}