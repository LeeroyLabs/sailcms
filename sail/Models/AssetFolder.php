<?php

namespace SailCMS\Models;

use SailCMS\Database\Model;
use SailCMS\Collection;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\PermissionException;
use SailCMS\Text;

/**
 *
 * @property string $slug
 * @property string $name
 * @property bool   $deletable
 * @property string $site_id
 *
 */
class AssetFolder extends Model
{
    protected string $collection = 'asset_folders';
    protected string $permissionGroup = 'asset';

    /**
     *
     * Get all folders
     *
     * @param  string  $siteId
     * @return Collection
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public static function folders(string $siteId = 'default'): Collection
    {
        $instance = new self;
        $instance->hasPermissions(true);

        return new Collection(self::query()->find(['site_id' => $siteId])->exec());
    }

    /**
     *
     * Create a new folder
     *
     * Result
     * 1 = success
     * 2 = permission denied
     * 3 = folder already exists
     *
     * @param  string  $folder
     * @param  string  $siteId
     * @return int
     *
     */
    public static function create(string $folder, string $siteId = 'default'): int
    {
        $instance = new self;

        try {
            $instance->hasPermissions();

            $txt = Text::from($folder);
            $name = $txt->capitalize()->value();
            $slug = $txt->slug()->value();
            $folderObj = self::getBy('name', $name);

            if ($folderObj) {
                return 3;
            }

            self::query()->insert([
                'name' => $name,
                'slug' => $slug,
                'deletable' => true,
                'site_id' => $siteId
            ]);

            return 1;
        } catch (ACLException|PermissionException|DatabaseException $e) {
            return 2;
        }
    }

    /**
     *
     * Delete a folder
     *
     * @param  string  $folder
     * @param  string  $siteId
     * @return void
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public static function delete(string $folder, string $siteId = 'default'): void
    {
        $instance = new self;
        $instance->hasPermissions();

        self::query()->deleteOne(['slug' => $folder, 'site_id' => $siteId]);
    }
}