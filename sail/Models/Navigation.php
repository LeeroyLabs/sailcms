<?php

namespace SailCMS\Models;

use RuntimeException;
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
 * @property string              $slug
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
     * @param string $title
     * @param array|Collection|NavigationStructure $structure
     * @param string $locale
     * @param string $siteId
     * @return string
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws NavigationException
     * @throws PermissionException
     */
    public function create(string $title, string $slug, array|Collection|NavigationStructure $structure, string $locale = 'en', string $siteId = ''): string
    {
        $this->hasPermissions();

        if (!is_object($structure) || get_class($structure) !== NavigationStructure::class) {
            $structure = new NavigationStructure($structure);
        }

        $siteId = $siteId ?: Sail::siteId();
        if (!$slug) {
            $slug = $title;
        }
        $slug = Text::from($slug)->slug()->value();
        $count = self::query()->count(['slug' => $slug]);

        // Set a number next to the name to make it unique
        if ($count > 0) {
            $slug .= '-' . Text::init()->random(4, false);
        }

        $this->insert([
            'title' => $title,
            'slug' => $slug,
            'structure' => $structure,
            'locale' => $locale,
            'site_id' => $siteId
        ]);

        return $slug;
    }

    /**
     *
     * Update existing navigation with given information and structure
     *
     * @param string $id
     * @param string $title
     * @param string $slug
     * @param array|Collection|NavigationStructure $structure
     * @param string $locale
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws NavigationException
     * @throws PermissionException
     */
    public function update(string $id, string $title, string $slug, array|Collection|NavigationStructure $structure, string $locale = 'en'): bool
    {
        $this->hasPermissions();

        if (!is_object($structure) || get_class($structure) !== NavigationStructure::class) {
            $structure = new NavigationStructure($structure);
        }

        if (!$slug) {
            $slug = $title;
        }
        $slug = Text::from($slug)->slug()->value();
        $count = self::query()->count(['slug' => $slug]);

        // Set a number next to the name to make it unique
        if ($count > 0) {
            $slug .= '-' . Text::init()->random(4, false);
        }

        $this->updateOne(['_id' => $this->ensureObjectId($id)], [
            '$set' => [
                'title' => $title,
                'slug' => $slug,
                'structure' => $structure,
                'locale' => $locale
            ]
        ]);

        return true;
    }

    /**
     *
     * Delete a navigation by ID
     *
     * @throws DatabaseException
     */
    public function delete(string $id): bool
    {
        self::query()->deleteById($id);
        return true;
    }

    /**
     *
     * Delete a navigation by name
     *
     * @param string $slug
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     */
    public function deleteByName(string $slug): bool
    {
        $this->hasPermissions();
        self::query()->deleteOne(['slug' => $slug]);
        return true;
    }

    /**
     *
     * Get a list of navigation
     *
     * @param string $sort
     * @param int $direction
     * @param string|null $locale
     * @param string|null $siteId
     * @return array|null
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     */
    public static function getList(string $sort = 'title', int $direction = Model::SORT_ASC, string $locale = null, string $siteId = null): ?array
    {
        self::query()->hasPermissions(true);

        $query = [];

        if ($siteId) {
            $query['site_id'] = $siteId;
        }
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
     * @param  string  $slug
     * @return Navigation|null
     * @throws DatabaseException
     *
     */
    public static function getBySlug(string $slug): ?Navigation
    {
        return self::getBy('slug', $slug);
    }
}