<?php

namespace SailCMS\Models;

use SailCMS\Collection;
use SailCMS\Database\Model;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\EntryException;
use SailCMS\Errors\NavigationException;
use SailCMS\Errors\PermissionException;
use SailCMS\Text;
use SailCMS\Types\NavigationStructure;

/**
 *
 * @property string              $title
 * @property string              $name
 * @property NavigationStructure $structure
 * @property string              $locale
 *
 */
class Navigation extends Model
{
    protected string $collection = 'navigations';
    protected string $permissionGroup = 'navigation';
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
     * @throws ACLException
     * @throws PermissionException
     * @throws EntryException
     *
     */
    public function create(string $name, array|Collection|NavigationStructure $structure, string $locale = 'en'): string
    {
        $this->hasPermissions();

        if (!is_object($structure) || get_class($structure) !== NavigationStructure::class) {
            $structure = new NavigationStructure($structure);
        }

        $title = $name;
        $name = Text::slugify($name);
        $count = self::query()->count(['name' => $name]);

        // Set a number next to the name to make it unique
        if ($count > 0) {
            $name .= '-' . Text::randomString(4, false);
        }

        $id = $this->insert([
            'title' => $title,
            'name' => $name,
            'structure' => $structure,
            'locale' => $locale
        ]);

        return $name;
    }

    /**
     *
     * Update existing navigation with given information and structure
     *
     * @param  string                                $name
     * @param  array|Collection|NavigationStructure  $structure
     * @param  string                                $locale
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws NavigationException
     * @throws PermissionException
     *
     */
    public function update(string $name, array|Collection|NavigationStructure $structure, string $locale = 'en'): bool
    {
        if (!is_object($structure) || get_class($structure) !== NavigationStructure::class) {
            $structure = new NavigationStructure($structure);
        }

        $this->updateOne(['name' => $name], [
            '$set' => [
                'title' => $name,
                'structure' => $structure,
                'locale' => $locale
            ]
        ]);

        return true;
    }

    /**
     *
     * Delete a navigation by name
     *
     * @param  string  $name
     * @return bool
     * @throws DatabaseException
     *
     */
    public function deleteByName(string $name): bool
    {
        self::query()->deleteOne(['name' => $name]);
        return true;
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