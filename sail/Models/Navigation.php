<?php

namespace SailCMS\Models;

use SailCMS\Collection;
use SailCMS\Database\Model;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\NavigationException;
use SailCMS\Types\NavigationElement;
use SailCMS\Types\NavigationStructure;

/**
 *
 * @property string              $name
 * @property NavigationStructure $structure
 * @property string              $locale
 *
 */
class Navigation extends Model
{
    protected string $collection = 'navigations';
    protected array $casting = [
        'structure' => NavigationStructure::class
    ];

    /**
     *
     * Create a navigation
     *
     * @param  string                                $name
     * @param  array|Collection|NavigationStructure  $structure
     * @param  string                                $locale
     * @return string
     * @throws DatabaseException
     * @throws NavigationException
     *
     */
    public function create(string $name, array|Collection|NavigationStructure $structure, string $locale = 'en'): string
    {
        if (!is_object($structure) || get_class($structure) !== NavigationStructure::class) {
            $structure = new NavigationStructure($structure);
        }

        \SailCMS\Debug::ray($structure);

        $id = $this->insert([
            'name' => $name,
            'structure' => $structure,
            'locale' => $locale
        ]);

        return (string)$id;
    }

    /**
     *
     * Get a navigation by id
     *
     * @param  string  $id
     * @return Navigation|null
     * @throws DatabaseException
     *
     */
    public static function getById(string $id): ?Navigation
    {
        return self::get($id);
    }

    /**
     *
     * Get a navigation by it's name
     *
     * @param  string  $name
     * @return Navigation|null
     * @throws DatabaseException
     *
     */
    public static function getByName(string $name): ?Navigation
    {
        return self::getBy('name', $name);
    }
}