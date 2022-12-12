<?php

namespace SailCMS\Models;

use MongoDB\BSON\ObjectId;
use SailCMS\Collection;
use SailCMS\Database\Model;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\EntryException;
use SailCMS\Errors\PermissionException;
use SailCMS\Sail;
use SailCMS\Text;
use SailCMS\Types\LocaleField;
use SailCMS\Types\QueryOptions;

class Category extends Model
{
    public LocaleField $name;
    public string $site_id;
    public string $slug;
    public int $order;
    public string $parent_id;

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
     * Return category object parsed for graphQL
     *
     * @return array|null
     *
     *
     */
    public function toGraphQL(): ?array
    {
        return [
            "_id" => $this->_id,
            "name" => $this->name->toDBObject(),
            "slug" => $this->slug,
            "parent_id" => $this->parent_id,
            "order" => $this->order,
            "children" => Collection::init()
        ];
    }

    /**
     *
     * Get a category by id
     *
     * @param ObjectId|string $id
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
     * @param string $slug
     * @param string $site_id
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
     * @param ObjectId|string $id
     * @param string|null $siteId
     * @return Collection
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    public static function getEntriesById(ObjectId|string $id, string $siteId = null): Collection
    {
        return Entry::findByCategoryId((string)$id, $siteId);
    }

    /**
     *
     * Alias for Entry's method for that
     *
     * @param string $slug
     * @param string|null $siteId
     * @return Collection
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    public static function getEntriesBySlug(string $slug, string $siteId = null): Collection
    {
        $siteId = $siteId ?? Sail::siteId();

        $record = static::getBySlug($slug, $siteId);

        $entries = Collection::init();
        if ($record) {
            $entries = Entry::findByCategoryId($record->_id);
        }

        return $entries;
    }

    /**
     *
     * Create a category
     *
     * @param LocaleField $name
     * @param string $parentId
     * @param string $siteId
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function create(LocaleField $name, string $parentId = '', string $siteId = 'main'): bool
    {
        $this->hasPermissions();

        // Count the total categories based on parent_id being present or not
        $count = $this->count(['site_id' => $siteId, 'parent_id' => $parentId]);
        $slug = Text::slugify($name->en);

        // Check that it does not exist for the site already
        $exists = $this->count(['slug' => $slug, 'site_id' => $siteId]);

        if ($exists > 0) {
            // Oops!
            throw new DatabaseException('Category with this name already exists', 0403);
        }

        // Set the order
        $count++;

        $this->insert([
            'name' => $name,
            'site_id' => $siteId,
            'slug' => $slug,
            'order' => $count,
            'parent_id' => $parentId
        ]);

        return true;
    }

    /**
     *
     * Update a category
     *
     * @param ObjectId|string $id
     * @param LocaleField $name
     * @param string $parent_id
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
     * @param ObjectId|string $id
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
     * @param string $slug
     * @param string $siteId
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public static function deleteBySlug(string $slug, string $siteId): bool
    {
        $instance = new static();
        $instance->hasPermissions();
        $record = $instance->findOne(['slug' => $slug, 'site_id' => $siteId])->exec();

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
     * @param string $parent
     * @param string $siteId
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function updateOrder(string $parent = '', string $siteId = 'main'): bool
    {
        $this->hasPermissions();

        $docs = $this->find(['parent_id' => $parent, 'site_id' => $siteId], QueryOptions::initWithSort(['order' => 1]))->exec();
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
     * @param string $parent
     * @param string $siteId
     * @return Collection
     * @throws DatabaseException
     *
     */
    public function getList(string $parent = '', string $siteId = 'main'): Collection
    {
        $query = ['site_id' => $siteId];

        if ($parent !== '') {
            $query['parent_id'] = $parent;
        }

        $opts = QueryOptions::initWithSort(['parent_id' => 1, 'order' => 1]);
        $key = ($parent === '') ? "{$siteId}_all" : $siteId . '_' . $parent;
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

            $item = $listCollection->find(function ($key, $cat) use ($id) {
                return ((string)$cat->_id === $id);
            });

            $item->children = $childrenList;
            $structured[] = $item;
        }

        // Remove indexes where the id is not at top level
        $final = [];

        foreach ($structured as $num => $tree) {
            $item = $listCollection->find(function ($key, $cat) use ($tree) {
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
     * @param Collection $categories
     * @param array $tree
     * @param string $id
     * @return array
     *
     */
    private function parseChildrenList(Collection $categories, array $tree, string $id = ''): array
    {
        $children = [];

        foreach ($tree[$id] as $_id) {
            $item = $categories->find(function ($key, $cat) use ($_id) {
                return ((string)$cat->_id === $_id);
            });

            $item->children = (count($tree[$_id]) === 0) ? [] : $this->parseChildrenList($categories, $tree, $_id);
            $children[] = $item;
        }

        return $children;
    }
}