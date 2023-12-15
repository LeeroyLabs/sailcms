<?php

namespace SailCMS\Models;

use ImagickException;
use JsonException;
use League\Flysystem\FilesystemException;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;
use SailCMS\Assets\Optimizer;
use SailCMS\Assets\Size;
use SailCMS\Assets\Transformer;
use SailCMS\Collection;
use SailCMS\Database\Model;
use SailCMS\Database\Traits\QueryObject;
use SailCMS\Debug;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\AssetException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\FileException;
use SailCMS\Errors\PermissionException;
use SailCMS\Internal\Filesystem;
use SailCMS\Locale;
use SailCMS\Middleware;
use SailCMS\Text;
use SailCMS\Types\Listing;
use SailCMS\Types\LocaleField;
use SailCMS\Types\MiddlewareType;
use SailCMS\Types\Pagination;
use SailCMS\Types\QueryOptions;

/**
 *
 * @property string      $user_id
 * @property string      $url
 * @property LocaleField $name;
 *
 */
class Bookmark extends Model
{
    protected string $collection = 'bookmarks';
    protected array $casting = [
        'name' => LocaleField::class
    ];

    /**
     *
     * Create a bookmark
     *
     * @param  string       $userId
     * @param  string       $url
     * @param  LocaleField  $name
     * @return bool
     * @throws DatabaseException
     *
     */
    public function add(string $userId, string $url, LocaleField $name): bool
    {
        $id = $this->insert([
            'user_id' => $userId,
            'url' => $url,
            'name' => $name
        ]);

        return (!empty($id));
    }

    /**
     *
     * Delete a bookmark
     *
     * @param  string  $userId
     * @param  string  $url
     * @return bool
     * @throws DatabaseException
     *
     */
    public function delete(string $userId, string $url): bool
    {
        $count = $this->deleteOne(['user_id' => $userId, 'url' => $url]);
        return ($count > 0);
    }

    /**
     *
     * Get list of bookmarks for given user
     *
     * @param  string  $userId
     * @return Collection
     * @throws DatabaseException
     *
     */
    public function getList(string $userId): Collection
    {
        return new Collection($this->find(['user_id' => $userId])->exec());
    }
}