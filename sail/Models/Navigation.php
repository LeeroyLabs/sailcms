<?php

namespace SailCMS\Models;

use SailCMS\Collection;
use SailCMS\Database\Model;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\EntryException;
use SailCMS\Errors\NavigationException;
use SailCMS\Errors\PermissionException;
use SailCMS\Sail;
use SailCMS\Text;
use SailCMS\Types\NavigationStructure;
use SailCMS\Types\QueryOptions;

/**
 *
 * @property string              $title
 * @property string              $name
 * @property NavigationStructure $structure
 * @property string              $locale
 * @property string              $site_id
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
     * @param  string                                $siteId
     * @return string
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws NavigationException
     * @throws PermissionException
     *
     */
    public function create(string $name, array|Collection|NavigationStructure $structure, string $locale = 'en', string $siteId = ''): string
    {
        $this->hasPermissions();

        if (!is_object($structure) || get_class($structure) !== NavigationStructure::class) {
            $structure = new NavigationStructure($structure);
        }

        $siteId = $siteId ?: Sail::siteId();
        $title = $name;
        $name = Text::from($name)->slug()->value();
        $count = self::query()->count(['name' => $name]);

        // Set a number next to the name to make it unique
        if ($count > 0) {
            $name .= '-' . Text::init()->random(4, false);
        }

        $id = $this->insert([
            'title' => $title,
            'name' => $name,
            'structure' => $structure,
            'locale' => $locale,
            'site_id' => $siteId
        ]);

        return $name;
    }

    /**
     *
     * Update existing navigation with given information and structure
     *
     * @param  string                                $id
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
    public function update(string $id, string $name, array|Collection|NavigationStructure $structure, string $locale = 'en'): bool
    {
        $this->hasPermissions();

        if (!is_object($structure) || get_class($structure) !== NavigationStructure::class) {
            $structure = new NavigationStructure($structure);
        }

        $this->updateOne(['_id' => $this->ensureObjectId($id)], [
            '$set' => [
                'title' => $name,
                'structure' => $structure,
                'locale' => $locale
            ]
        ]);

        return true;
    }

    public function delete(string $id): bool
    {
        self::query()->deleteById($id);
        return true;
    }

    /**
     *
     * Delete a navigation by name
     *
     * @param  string  $name
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function deleteByName(string $name): bool
    {
        $this->hasPermissions();
        self::query()->deleteOne(['name' => $name]);
        return true;
    }

    /**
     *
     * Get a list of navigation
     *
     * @param  string       $sort
     * @param  int          $direction
     * @param  string|null  $locale
     * @param  string       $siteId
     * @return array|null
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public static function getList(string $sort = 'title', int $direction = Model::SORT_ASC, string $locale = null, string $siteId = ''): ?array
    {
        self::query()->hasPermissions(true);

        $siteId = $siteId ?: Sail::siteId();
        $query = ['site_id' => $siteId];

        if ($locale) {
            $query['locale'] = $locale;
        }

        $opt = QueryOptions::initWithSort([$sort => $direction]);

        return (new Navigation())->find($query, $opt)->exec();
    }

    /**
     *
     * Get a navigation by id
     *
     * @param  string  $id
     * @return Navigation|null
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public static function getById(string $id): ?Navigation
    {
        self::query()->hasPermissions();
        return self::get($id);
    }

    /**
     *
     * Get a navigation by its name
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