<?php

namespace SailCMS\Models;

use SailCMS\Collection;
use SailCMS\Database\Model;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\PermissionException;
use SailCMS\Text;

/**
 *
 * @property string $name
 * @property string $slug
 *
 */
class UserGroup extends Model
{
    protected string $collection = 'user_groups';
    protected string $permissionGroup = 'group';

    /**
     *
     * Get all groups
     *
     * @return Collection
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public static function getAll(): Collection
    {
        (new self())->hasPermissions(true);
        return new Collection(self::query()->find()->exec());
    }

    /**
     *
     * Create a group
     *
     * @param  string  $name
     * @return string
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public static function create(string $name): string
    {
        (new self())->hasPermissions();

        $group = self::getBy('name', $name);

        if ($group) {
            throw new DatabaseException('Group already exists', 0403);
        }

        $rec = self::query()->insert([
            'name' => $name,
            'slug' => Text::from($name)->slug()->value()
        ]);

        if ($rec) {
            return (string)$rec;
        }

        return '';
    }

    /**
     *
     * Update a group's name (but not its slug)
     *
     * @param  string  $id
     * @param  string  $name
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public static function update(string $id, string $name): bool
    {
        $instance = new self;
        $instance->hasPermissions();

        self::query()->updateOne(['_id' => $instance->ensureObjectId($id)], ['$set' => ['name' => $name]]);
        return true;
    }

    /**
     *
     * Delete a group by its id
     *
     * @param  string  $id
     * @return bool
     * @throws DatabaseException
     * @throws ACLException
     * @throws PermissionException
     *
     */
    public static function delete(string $id): bool
    {
        (new self())->hasPermissions();

        $group = self::get($id);

        if (!$group) {
            return false;
        }

        self::query()->deleteById($id);

        // Remove everyone from that group
        User::removeGroupFromAll($group->slug);

        return true;
    }
}