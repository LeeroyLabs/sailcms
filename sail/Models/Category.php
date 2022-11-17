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

    public function __construct()
    {
        parent::__construct('categories', 0);
    }

    public function init(): void
    {
        $this->setPermissionGroup('categories');
    }

    public function fields(bool $fetchAllFields = false): array
    {
        return ['_id', 'name', 'site_id', 'slug', 'parent_id', 'order'];
    }

    protected function processOnFetch(string $field, mixed $value): mixed
    {
        if ($field === 'name') {
            return new LocaleField($value);
        }

        return $value;
    }

    /**
     *
     * Get a category by id
     *
     * @param  ObjectId|string  $id
     * @return Category|null
     * @throws DatabaseException
     *
     */
    public static function getById(ObjectId|string $id): ?Category
    {
        $instance = new static();
        return $instance->findById($id)->exec((string)$id);
    }

    /**
     *
     * Get a category by slug (and site id)
     *
     * @param  string  $slug
     * @param  string  $site_id
     * @return Category|null
     * @throws DatabaseException
     *
     */
    public static function getBySlug(string $slug, string $site_id): ?Category
    {
        $instance = new static();
        return $instance->findOne(['slug' => $slug, 'site_id' => $site_id])->exec("{$site_id}_{$slug}");
    }

    /**
     *
     * Get all entries that are in the given category id
     *
     * @param  ObjectId|string  $id
     * @return Collection
     * @throws DatabaseException
     *
     */
    public static function getEntriesById(ObjectId|string $id): Collection
    {
        $instance = new static();
        $record = $instance->findById($id)->exec((string)$id);

        if ($record) {
            // TODO: Call method in Entry to load from the given category slug and site_id
        }

        return Collection::init();
    }

    /**
     *
     * Alias for Entry's method for that
     *
     * @param  string  $slug
     * @return Collection
     *
     */
    public static function getEntriesBySlug(string $slug): Collection
    {
        // TODO: Call method in Entry to load from the given category slug and site_id

        return Collection::init();
    }

    /**
     *
     * Create a category
     *
     * @param  LocaleField  $name
     * @param  string       $parent_id
     * @param  string       $site_id
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function create(LocaleField $name, string $parent_id = '', string $site_id = 'main'): bool
    {
        $this->hasPermissions();

        // Count the total categories based on parent_id being present or not
        $count = $this->count(['site_id' => $site_id, 'parent_id' => $parent_id]);
        $slug = Text::slugify($name->en);

        // Check that it does not exist for the site already
        $exists = $this->count(['slug' => $slug, 'site_id' => $site_id]);

        if ($exists > 0) {
            // Oops!
            throw new DatabaseException('Category with this name already exists', 0403);
        }

        // Set the order
        $count++;

        $this->insert([
            'name' => $name,
            'site_id' => $site_id,
            'slug' => $slug,
            'order' => $count,
            'parent_id' => $parent_id
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

        $record = $this->findById($id)->exec((string)$id);

        $id = $this->ensureObjectId($id);

        if ($record) {
            $count = $record->order;

            if ($record->parent_id !== $parent_id) {
                $count = $this->count(['site_id' => $record->site_id, 'parent_id' => $parent_id]);
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
     * Delete a category by slug
     *
     * @param  string  $slug
     * @param  string  $site_id
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public static function deleteBySlug(string $slug, string $site_id): bool
    {
        $instance = new static();
        $instance->hasPermissions();
        $record = $instance->findOne(['slug' => $slug, 'site_id' => $site_id])->exec();

        if ($record) {
            $instance->deleteById($record->_id);
            $instance->updateMany(['parent_id' => (string)$record->_id], ['$set' => ['parent_id' => '']]);
            $instance->updateOrder('');
            return true;
        }

        return false;
    }

    /**
     *
     * Update order for all sub categories
     *
     * @param  string  $parent
     * @param  string  $site_id
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function updateOrder(string $parent = '', string $site_id = 'main'): bool
    {
        $this->hasPermissions();

        $docs = $this->find(['parent_id' => $parent, 'site_id' => $site_id], QueryOptions::initWithSort(['order' => 1]))->exec();
        $writes = [];

        foreach ($docs as $num => $doc) {
            $writes[] = [
                'updateOne' => [
                    ['_id' => $doc->_id],
                    ['$set' => ['order' => ($num + 1)]]
                ]
            ];
        }

        // Bulk write everything, performance++
        $this->bulkWrite($writes);
        return true;
    }

    /**
     *
     * Get tree list of categories
     *
     * @param  string  $parent
     * @param  string  $site_id
     * @return Collection
     * @throws DatabaseException
     *
     */
    public function getList(string $parent = '', string $site_id = 'main'): Collection
    {
        $query = ['site_id' => $site_id];

        if ($parent !== '') {
            $query['parent_id'] = $parent;
        }

        $opts = QueryOptions::initWithSort(['parent_id' => 1, 'order' => 1]);
        $key = ($parent === '') ? "{$site_id}_all" : $site_id . '_' . $parent;
        $list = $this->find($query, $opts)->exec($key);
        $basicTree = [];

        foreach ($list as $num => $item) {
            $id = (string)$item->_id;

            // Might have been created already
            if (!isset($final[$id])) {
                $basicTree[$id] = [];
            }

            if ($item->parent_id !== '') {
                // Create if it does not exist
                if (!isset($basicTree[$item->parent_id])) {
                    $basicTree[$item->parent_id] = [];
                }

                $basicTree[$item->parent_id][] = $id;
            }
        }

        $structured = [];
        $listCollection = new Collection($list);

        foreach ($basicTree as $id => $children) {
            $childrenList = $this->parseChildrenList($listCollection, $basicTree, $id);

            $item = $listCollection->find(function ($key, $cat) use ($id)
            {
                return ((string)$cat->_id === $id);
            });

            $item->children = $childrenList;
            $structured[] = $item;
        }

        // Remove indexes where the id is not at top level
        $final = [];

        foreach ($structured as $num => $tree) {
            $item = $listCollection->find(function ($key, $cat) use ($tree)
            {
                return ((string)$cat->_id === (string)$tree->_id);
            });

            if ($item && $item->parent_id === $parent) {
                $final[] = $tree;
            }
        }

        return new Collection($final);
    }

    /**
     *
     * Parse the tree of children
     *
     * @param  Collection  $categories
     * @param  array       $tree
     * @param  string      $id
     * @return array
     *
     */
    private function parseChildrenList(Collection $categories, array $tree, string $id = ''): array
    {
        $children = [];

        foreach ($tree[$id] as $_id) {
            $item = $categories->find(function ($key, $cat) use ($_id)
            {
                return ((string)$cat->_id === $_id);
            });

            $item->children = (count($tree[$_id]) === 0) ? [] : $this->parseChildrenList($categories, $tree, $_id);
            $children[] = $item;
        }

        return $children;
    }
}