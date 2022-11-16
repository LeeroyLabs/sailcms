<?php

namespace SailCMS\Models;

use MongoDB\BSON\ObjectId;
use SailCMS\Collection;
use SailCMS\Database\Model;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\PermissionException;
use SailCMS\Sail;
use SailCMS\Text;
use SailCMS\Types\CategoryItem;
use SailCMS\Types\LocaleField;
use SailCMS\Types\QueryOptions;

class Category extends Model
{
    public LocaleField $name;
    public string $site_id;
    public string $slug;
    public int $order;
    public string $parent_id;

    public function fields(bool $fetchAllFields = false): array
    {
        return ['_id', 'name', 'site_id', 'slug', 'parent_id', 'order'];
    }

    /**
     *
     * Create a category
     *
     * @param  LocaleField  $name
     * @param  string       $site_id
     * @param  string       $parent_id
     * @return bool
     * @throws DatabaseException
     * @throws ACLException
     * @throws PermissionException
     *
     */
    public function create(LocaleField $name, string $parent_id = ''): bool
    {
        $this->hasPermissions();

        // Count the total categories based on parent_id being present or not
        if ($parent_id !== '') {
            $count = $this->count(['site_id' => Sail::siteId(), 'parent_id' => $parent_id]);
        } else {
            $count = $this->count(['site_id' => Sail::siteId()]);
        }

        $slug = Text::slugify($name->en);

        // Check that it does not exist for the site already
        $exists = $this->count(['slug' => $slug, 'site_id' => Sail::siteId()]);

        if ($exists > 0) {
            // Oops!
            throw new DatabaseException('Category with this name already exists', 0403);
        }

        // Set the order
        $count++;

        $this->insert([
            'name' => $name,
            'site_id' => Sail::siteId(),
            'slug' => $slug,
            'order' => $count,
            'parent_id' => ($parent_id !== '') ? $parent_id : null
        ]);

        return true;
    }

    /**
     *
     * Update a category
     *
     * @param  ObjectId|string  $id
     * @param  LocaleField      $name
     * @param  string           $parent_id
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function update(ObjectId|string $id, LocaleField $name, string $parent_id = ''): bool
    {
        $this->hasPermissions();

        $record = $this->findById($id)->exec();

        if ($record) {
            $count = $record->order;

            if ($record->parent_id !== $parent_id) {
                if ($parent_id !== '') {
                    $count = $this->count(['site_id' => $record->site_id, 'parent_id' => $parent_id]);
                } else {
                    $count = $this->count(['site_id' => $record->site_id]);
                }

                $count++;
            }

            $id = $this->ensureObjectId($id);
            $this->updateOne(['_id' => $id], [
                '$set' => [
                    'name' => $name,
                    'parent_id' => $parent_id,
                    'order' => $count
                ]
            ]);

            return true;
        }

        return false;
    }

    /**
     *
     * Delete a category (reassign sub categories to top level
     *
     * @param  ObjectId|string  $id
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function delete(ObjectId|string $id): bool
    {
        $this->hasPermissions();

        $record = $this->findById($id)->exec();

        if ($record) {
            $this->deleteById($id);
            $this->updateMany(['parent_id' => (string)$id], ['$set' => ['parent_id' => '']]);
            $this->updateOrder('');
            return true;
        }

        return false;
    }

    /**
     *
     * Update order for all sub categories
     *
     * @param  string  $parent
     * @return void
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function updateOrder(string $parent = ''): void
    {
        $this->hasPermissions();

        $docs = $this->find(['parent_id' => $parent], QueryOptions::initWithSort(['order' => 1]))->exec();
        $writes = [];

        foreach ($docs as $num => $doc) {
            $writes[] = [
                'updateOne' => [
                    'filter' => ['_id' => $doc->_id],
                    'update' => ['$set' => ['order' => ($num + 1)]]
                ]
            ];
        }

        // Bulk write everything, performance++
        $this->bulkWrite($writes);
    }

    public function getList(string $parent = ''): Collection
    {
        $query = [];

        if ($parent !== '') {
            $query = ['parent_id' => $parent];
        }

        $list = $this->find($query, QueryOptions::initWithSort(['parent_id' => 1, 'order' => 1]))->exec();
        $categories = Collection::init();


        #foreach ($list as $cat) {
        #}
    }
}