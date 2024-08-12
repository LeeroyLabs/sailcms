<?php

namespace SailCMS\Database\Traits;

use Exception;
use JsonException;
use MongoDB\BSON\ObjectId;
use MongoDB\BulkWriteResult;
use MongoDB\Model\BSONArray;
use SailCMS\Cache;
use SailCMS\Collection;
use SailCMS\Database\Model;
use SailCMS\Debug;
use SailCMS\Errors\DatabaseException;
use SailCMS\Types\QueryOptions;

trait QueryObject
{
    // Add Relationships feature
    use Relationships;

    // Query operation Data
    private bool $currentShowAll = false;
    private string $currentOp = '';
    private array $currentQuery = [];
    private array $currentSort = [];
    private array $currentProjection = [];
    private int $currentLimit = 10_000;
    private int $currentSkip = 0;
    private array $currentPopulation = [];
    private string $currentField = '';
    private string $currentCollation = '';
    private bool $isSingle = false;

    /**
     *
     * Execute a query call (cannot be run with a mutation type method)
     *
     * @param  string  $cacheKey
     * @param  int     $cacheTTL
     * @return QueryObject|array|null (SailCMS\Database\Model)|array|null
     * @throws DatabaseException
     *
     */
    protected function exec(string $cacheKey = '', int $cacheTTL = Cache::TTL_WEEK): static|array|null
    {
        $qt = Debug::startQuery();
        $options = [];
        $docs = [];

        $cached = null;
        $usedCacheKey = '';

        if ($cacheKey !== '') {
            $usedCacheKey = $this->assembleCacheKey($cacheKey);

            try {
                $cached = Cache::get($usedCacheKey);

                if (is_array($cached)) {
                    // Array, CastBack every item
                    foreach ($cached as $num => $cache) {
                        $cached[$num] = $this->transformDocToModel($cache);
                    }
                } elseif (is_object($cached)) {
                    // CastBack
                    $cached = $this->transformDocToModel($cached);
                }
            } catch (JsonException $e) {
                // already null
            }
        }

        if (!empty($cached)) {
            return $cached;
        }

        if (count($this->currentSort) > 0) {
            $options['sort'] = $this->currentSort;
        }

        if (count($this->currentProjection) > 0) {
            $options['projection'] = $this->currentProjection;
        }

        if ($this->currentCollation !== '') {
            $options['collation'] = [
                'locale' => $this->currentCollation,
                'strength' => 3
            ];
        }

        $activeCollectionOrView = $this->use_view ? $this->active_view : $this->active_collection;

        // Single query
        if ($this->isSingle) {
            $result = $activeCollectionOrView->{$this->currentOp}($this->currentQuery, $options);
            if ($result) {
                $doc = $this->transformDocToModel($result);

                // Run all population requests
                foreach ($this->currentPopulation as $populate) {
                    $doc = self::parsePopulate($doc, $populate);
                }

                $this->debugCall('', $qt);
                $this->clearOps();

                if ($usedCacheKey !== '') {
                    try {
                        Cache::set($usedCacheKey, $doc, $cacheTTL);
                    } catch (JsonException $e) {
                        // Do nothing about it
                    }
                }

                // Fetch all relationships
                $doc = $this->fetchRelationships($doc);

                $doc->exists = true;
                $doc->isDirty = false;
                $doc->dirtyFields = [];
                return $doc;
            }

            $this->debugCall('', $qt);
            $this->clearOps();
            return null;
        }

        // Multi
        if ($this->currentSkip > 0) {
            $options['skip'] = $this->currentSkip;
        }

        if ($this->currentLimit > 0) {
            $options['limit'] = $this->currentLimit;
        }

        try {
            if ($this->currentOp === 'distinct') {
                $results = $activeCollectionOrView->distinct($this->currentField, $this->currentQuery, $options);
                return $results;
            }

            $results = $activeCollectionOrView->{$this->currentOp}($this->currentQuery, $options);
        } catch (Exception $e) {
            throw new DatabaseException('0500: ' . $e->getMessage(), 0500);
        }

        foreach ($results as $result) {
            $doc = $this->transformDocToModel($result);

            // Run all population requests
            foreach ($this->currentPopulation as $populate) {
                $instance = new $populate['class']();
                $field = $populate['field'];
                $target = $populate['targetField'];
                $subpop = $populate['subpopulates'];

                // Make sure to run only if field is not null and not empty string
                if ($doc->{$field} !== null && $doc->{$field} !== '') {
                    $list = [];
                    $targetList = [];
                    $is_array = false;

                    if (is_object($doc->{$field}) && get_class($doc->{$field}) === Collection::class) {
                        $targetList = $doc->{$field}->unwrap();
                        $is_array = true;
                    } elseif (is_array($doc->{$field})) {
                        $targetList = $doc->{$field};
                        $is_array = true;
                    }

                    if ($is_array) {
                        foreach ($targetList as $item) {
                            if (!empty($item) && !is_object($item)) {
                                $obj = $instance->findById($item);

                                if (count($subpop) >= 1) {
                                    foreach ($subpop as $pop) {
                                        $obj->populate($pop[0], $pop[1], $pop[2], $pop[3] ?? []);
                                    }
                                }

                                $list[] = $obj->exec();
                            }
                        }

                        $doc->{$target} = new Collection($list);
                    } else {
                        if (!empty($doc->{$field}) && !is_object($doc->{$field})) {
                            $obj = $instance->findById($doc->{$field});

                            if (count($subpop) >= 1) {
                                foreach ($subpop as $pop) {
                                    $obj->populate($pop[0], $pop[1], $pop[2], $pop[3] ?? []);
                                }
                            }

                            $doc->{$target} = $obj->exec();
                        }
                    }
                } else {
                    // Nullify the field, most probably going to be called from GraphQL
                    $doc->{$target} = null;
                }
            }

            // Fetch all relationships
            $doc = $this->fetchRelationships($doc);

            $doc->exists = true;
            $doc->isDirty = false;
            $doc->dirtyFields = [];
            $docs[] = $doc;
        }

        if ($usedCacheKey !== '') {
            try {
                Cache::set($usedCacheKey, $docs, $cacheTTL);
            } catch (JsonException $e) {
                // Do nothing about it
            }
        }

        $this->debugCall('', $qt);
        $this->clearOps();
        return $docs;
    }

    /**
     *
     * Get all records without any limits
     * Note: Be careful with the use of this method
     * Note: This is limited to 10M records
     *
     * @return Collection
     * @throws DatabaseException
     *
     */
    public static function allRecords(): Collection
    {
        return new Collection(self::query()->find()->limit(10_000_000)->exec());
    }

    /**
     *
     * Get a document count of current collection
     *
     * @param  array  $query
     * @return int
     *
     */
    public static function documentCount(array $query = []): int
    {
        return self::query()->count($query);
    }

    /**
     *
     * Get Last record added to collection
     *
     * @return static|null
     * @throws DatabaseException
     *
     */
    public static function last(): ?static
    {
        $records = self::query()->find()->sort(['_id' => -1])->limit(1)->exec();
        return $records[0] ?? null;
    }

    /**
     *
     * Get first record added to collection
     *
     * @return static|null
     * @throws DatabaseException
     *
     */
    public static function first(): ?static
    {
        $records = self::query()->find()->sort(['_id' => 1])->limit(1)->exec();
        return $records[0] ?? null;
    }

    /**
     *
     * Automatically populate a field when it is fetched from the database
     * (must be an ObjectId or a string representation)
     *
     * @param  string  $field
     * @param  string  $target
     * @param  string  $model
     * @param  array   $subpopulate
     * @return static
     *
     */
    protected function populate(string $field, string $target, string $model, array $subpopulate = []): static
    {
        $this->currentPopulation[] = [
            'field' => $field,
            'targetField' => $target,
            'class' => $model,
            'subpopulates' => $subpopulate
        ];

        return $this;
    }

    /**
     *
     * Set limit for the query
     *
     * @param  int  $limit
     * @return static
     *
     */
    protected function limit(int $limit): static
    {
        $this->currentLimit = $limit;
        return $this;
    }

    /**
     *
     * Skip for the query
     *
     * @param  int  $skip
     * @return static
     *
     */
    protected function skip(int $skip): static
    {
        $this->currentSkip = $skip;
        return $this;
    }

    /**
     *
     * Setup projection for the query
     *
     * @param  array|Collection  $projection
     * @return static
     *
     */
    protected function project(array|Collection $projection): static
    {
        if (is_object($projection)) {
            $projection = $projection->unwrap();
        }

        $this->currentProjection = $projection;
        return $this;
    }

    /**
     *
     * Set collation for the query
     *
     * @param  string  $locale
     * @return static
     *
     */
    protected function collation(string $locale): static
    {
        $this->currentCollation = $locale;
        return $this;
    }

    /**
     *
     * Set the sorting for the query
     *
     * @param  array|Collection  $sort
     * @return static
     *
     */
    protected function sort(array|Collection $sort): static
    {
        if (!is_array($sort)) {
            $sort = $sort->unwrap();
        }

        $this->currentSort = $sort;
        return $this;
    }

    /**
     *
     * Find by id
     *
     * @param  string|ObjectId    $id
     * @param  QueryOptions|null  $options
     * @return static
     *
     */
    protected function findById(string|ObjectId $id, QueryOptions|null $options = null): static
    {
        $_id = $this->ensureObjectId($id);

        if (!$options) {
            $options = QueryOptions::init(null, 0, 1);
        }

        $this->currentOp = 'findOne';
        $this->isSingle = true;
        $this->currentQuery = ['_id' => $_id];
        $this->currentSort = [];
        $this->currentProjection = $options->projection ?? [];
        return $this;
    }

    /**
     *
     * Find many records
     *
     * @param  array              $query
     * @param  QueryOptions|null  $options
     * @return static
     *
     */
    protected function find(array $query = [], QueryOptions|null $options = null): static
    {
        if (!$options) {
            $options = QueryOptions::init();
        }

        $this->currentOp = 'find';
        $this->isSingle = false;
        $this->currentQuery = $query;
        $this->currentSkip = $options->skip;
        $this->currentLimit = $options->limit;
        $this->currentSort = $options->sort ?? [];
        $this->currentProjection = $options->projection ?? [];
        $this->currentCollation = $options->collation;
        return $this;
    }

    /**
     *
     * Find one
     *
     * @param  array              $query
     * @param  QueryOptions|null  $options
     * @return static
     *
     */
    protected function findOne(array $query, QueryOptions|null $options = null): static
    {
        if (!$options) {
            $options = QueryOptions::init();
        }

        $this->currentOp = 'findOne';
        $this->isSingle = true;
        $this->currentQuery = $query;
        $this->currentLimit = 1;
        $this->currentSort = $options->sort ?? [];
        $this->currentProjection = $options->projection ?? [];
        return $this;
    }

    /**
     *
     * Find distinct documents
     *
     * @param  string             $field
     * @param  array              $query
     * @param  QueryOptions|null  $options
     * @return static
     *
     */
    protected function distinct(string $field, array $query, QueryOptions|null $options = null): static
    {
        if (!$options) {
            $options = QueryOptions::init();
        }

        $this->currentOp = 'distinct';
        $this->currentField = $field;
        $this->isSingle = false;
        $this->currentQuery = $query;
        $this->currentSkip = $options->skip;
        $this->currentLimit = $options->limit;
        $this->currentSort = $options->sort ?? [];
        $this->currentProjection = $options->projection ?? [];
        $this->currentCollation = $options->collation;
        return $this;
    }

    /**
     *
     * Run an aggregate request
     *
     * @param  array  $pipeline
     * @return array
     * @throws DatabaseException
     *
     */
    protected function aggregate(array $pipeline): array
    {
        $qt = Debug::startQuery();

        try {
            $results = $this->active_collection->aggregate($pipeline);
            $docs = [];

            foreach ($results as $result) {
                $docs[] = $this->transformDocToModel($result);
            }

            $this->debugCall('aggregate', $qt, ['pipeline' => $pipeline]);
            return $docs;
        } catch (Exception $e) {
            throw new DatabaseException('0500: ' . $e->getMessage(), 0500);
        }
    }

    /**
     *
     * Insert a record and return its ID
     *
     * @param  object|array  $doc
     * @return mixed|void
     * @throws DatabaseException
     *
     */
    protected function insert(object|array $doc)
    {
        $qt = Debug::startQuery();

        try {
            $doc = $this->prepareForWrite($doc);

            // Run Validators
            $this->runValidators((object)$doc);
            $id = $this->active_collection->insertOne($doc)->getInsertedId();

            $this->clearCacheForModel();
            $this->debugCall('insert', $qt);
            $this->log('insert', ['query' => $doc], true);
            return $id;
        } catch (Exception $e) {
            $this->log('insert', ['query' => $doc], false);
            throw new DatabaseException('0500: ' . $e->getMessage(), 0500);
        }
    }

    /**
     *
     * Insert many records and return the ids
     *
     * @param  array  $docs
     * @return ObjectId[]
     * @throws DatabaseException
     *
     */
    protected function insertMany(array $docs): array
    {
        $qt = Debug::startQuery();

        try {
            foreach ($docs as $num => $doc) {
                // Run Validators
                $this->runValidators((object)$doc);

                $docs[$num] = $this->prepareForWrite($doc);
            }

            $ids = $this->active_collection->insertMany($docs)->getInsertedIds();

            $this->clearCacheForModel();
            $this->debugCall('insertMany', $qt);
            $this->log('insertMany', ['query' => $docs], true);
            return $ids;
        } catch (Exception $e) {
            $this->log('insertMany', ['query' => $docs], false);
            throw new DatabaseException('0500: ' . $e->getMessage(), 0500);
        }
    }

    /**
     *
     * Bulk write to database
     *
     * @param  array  $writes
     * @return BulkWriteResult
     * @throws DatabaseException
     *
     */
    protected function bulkWrite(array $writes): BulkWriteResult
    {
        $qt = Debug::startQuery();

        try {
            $res = $this->active_collection->bulkWrite($writes);

            $this->clearCacheForModel();
            $this->debugCall('bulkWrite', $qt);
            $this->log('bulkWrite', ['query' => $writes], true);
            return $res;
        } catch (Exception $e) {
            $this->log('bulkWrite', ['query' => $writes], false);
            throw new DatabaseException('0500: ' . $e->getMessage(), 0500);
        }
    }

    /**
     *
     * Update a single record
     *
     * @param  array  $query
     * @param  array  $update
     * @return int
     * @throws DatabaseException
     *
     */
    protected function updateOne(array $query, array $update): int
    {
        $qt = Debug::startQuery();

        try {
            if (isset($update['$set'])) {
                // Run Validators
                $this->runValidators((object)$update['$set']);

                $update['$set'] = $this->prepareForWrite($update['$set']);
            }

            $count = $this->active_collection->updateOne($query, $update)->getModifiedCount();

            $this->clearCacheForModel();
            $this->currentLimit = 1;
            $this->debugCall('updateOne', $qt, ['query' => $query, 'update' => $update]);
            $this->log('updateOne', ['query' => $query, 'update' => $update], true);
            return $count;
        } catch (Exception $e) {
            $this->log('updateOne', ['query' => $query, 'update' => $update], false);
            throw new DatabaseException('0500: ' . $e->getMessage(), 0500);
        }
    }

    /**
     *
     * Update many records
     *
     * @param  array  $query
     * @param  array  $update
     * @return int
     * @throws DatabaseException
     *
     */
    protected function updateMany(array $query, array $update): int
    {
        $qt = Debug::startQuery();

        try {
            if (isset($update['$set'])) {
                // Run Validators
                $this->runValidators((object)$update['$set']);
                $update['$set'] = $this->prepareForWrite($update['$set']);
            }

            $count = $this->active_collection->updateMany($query, $update)->getModifiedCount();

            $this->clearCacheForModel();
            $this->debugCall('updateMany', $qt, ['query' => $query, 'update' => $update]);
            $this->log('updateMany', ['query' => $query, 'update' => $update], true);
            return $count;
        } catch (Exception $e) {
            $this->log('updateMany', ['query' => $query, 'update' => $update], false);
            throw new DatabaseException('0500: ' . $e->getMessage(), 0500);
        }
    }

    /**
     *
     * Quickly update a record's given field with given value
     *
     * @param  string|ObjectId  $id
     * @param  string|array     $field
     * @param  mixed            $value
     * @return bool
     * @throws DatabaseException
     * @throws Exception
     *
     */
    public function quickUpdate(string|ObjectId $id, string|array $field, mixed $value): bool
    {
        if (is_array($field)) {
            // Force $value to be an array and to be of same length
            if (!is_array($value)) {
                throw new DatabaseException('Since the field argument is an array, the value argument must also be an array', 0400);
            }

            if (count($value) !== count($field)) {
                throw new DatabaseException('The value argument must be an array of the same length has the field argument', 0400);
            }

            // Build update array
            $update = array_combine($field, $value);

            // Validate it
            $this->runValidators((object)$update);
            $update = $this->prepareForWrite($update);

            $updated = $this->updateOne(['_id' => $this->ensureObjectId($id)], ['$set' => $update]);
            $this->log('quickUpdate', ['query' => ['_id' => (string)$id, 'field' => $field, 'value' => $value]], ($updated > 0));
            return ($updated > 0);
        }

        // Validate it
        $this->runValidators((object)[$field => $value]);
        $update = $this->prepareForWrite([$field => $value]);

        $updated = $this->updateOne(['_id' => $this->ensureObjectId($id)], ['$set' => $update]);
        $this->log('quickUpdate', ['query' => ['_id' => (string)$id, 'field' => $field, 'value' => $value]], ($updated > 0));
        return ($updated > 0);
    }

    /**
     *
     * Dump the query to play it on mongo db
     *
     * @return string
     *
     */
    public function dumpQuery(): string
    {
        $sQuery = json_encode($this->currentQuery);

        if (count($this->currentPopulation) > 0) {
            // aggregate
            $dump = "db.getCollection('{$this->collection}').aggregate([";
            foreach ($this->currentPopulation as $index => $populate) {
                $dump .= $this->dumpPopulate($populate);

                if ($index === 0) {
                    $dump .= ", {\$match: $sQuery}";
                }
                if ($index !== array_key_last($this->currentPopulation)) {
                    $dump .= ",";
                }
            }

            if ($this->currentSkip > 0) {
                $dump .= ",{ \$skip: {$this->currentSkip} }";
            }
            if ($this->currentLimit) {
                $dump .= ",{ \$limit: {$this->currentLimit} }";
            }

            $dump .= "]);";
        } else {
            $dump = "db.getCollection('{$this->collection}').{$this->currentOp}($sQuery";

            if (count($this->currentProjection) > 0) {
                $sProjection = json_encode($this->currentProjection);
                $dump .= ", $sProjection";
            }

            $dump .= ")";

            if ($this->currentCollation) {
                $dump .= ".collation({$this->currentCollation})";
            }
            if (count($this->currentSort) > 0) {
                $sSort = json_encode($this->currentSort);
                $dump .= ".sort($sSort)";
            }
            if ($this->currentLimit) {
                $dump .= ".limit({$this->currentLimit})";
            }
            if ($this->currentSkip > 0) {
                $dump .= ".skip({$this->currentSkip})";
            }
        }

        return $dump;
    }

    /**
     *
     * Delete a record
     *
     * @param  array  $query
     * @return int
     * @throws DatabaseException
     *
     */
    protected function deleteOne(array $query): int
    {
        $qt = Debug::startQuery();

        try {
            $count = $this->active_collection->deleteOne($query)->getDeletedCount();

            $this->clearCacheForModel();
            $this->currentLimit = 1;
            $this->debugCall('deleteOne', $qt, ['query' => $query]);
            $this->log('deleteOne', ['query' => $query], true);
            return $count;
        } catch (Exception $e) {
            $this->log('deleteOne', ['query' => $query], false);
            throw new DatabaseException('0500: ' . $e->getMessage(), 0500);
        }
    }

    /**
     *
     * Delete many records
     *
     * @param  array  $query
     * @return int
     * @throws DatabaseException
     *
     */
    protected function deleteMany(array $query): int
    {
        $qt = Debug::startQuery();

        try {
            $count = $this->active_collection->deleteMany($query)->getDeletedCount();

            $this->clearCacheForModel();
            $this->debugCall('deleteMany', $qt, ['query' => $query]);
            $this->log('deleteById', ['query' => $query], true);
            return $count;
        } catch (Exception $e) {
            $this->log('deleteMany', ['query' => $query], false);
            throw new DatabaseException('0500: ' . $e->getMessage(), 0500);
        }
    }

    /**
     *
     * Delete a record by its ID
     *
     * @param  string|ObjectId  $id
     * @return int
     * @throws DatabaseException
     *
     */
    protected function deleteById(string|ObjectId $id): int
    {
        $qt = Debug::startQuery();
        $id = $this->ensureObjectId($id);

        try {
            $count = $this->active_collection->deleteOne(['_id' => $id])->getDeletedCount();

            $this->clearCacheForModel();
            $this->debugCall('deleteById', $qt, ['query' => ['_id' => $id]]);
            $this->log('deleteById', ['query' => $id], true);
            return $count;
        } catch (Exception $e) {
            $this->log('deleteById', ['query' => $id], false);
            throw new DatabaseException('0500: ' . $e->getMessage(), 0500);
        }
    }

    /**
     *
     * Count the number of records that match the query
     *
     * @param  array  $query
     * @return int
     *
     */
    protected function count(array $query): int
    {
        $qt = Debug::startQuery();
        $count = $this->active_collection->countDocuments($query);

        $this->debugCall('count', $qt, ['query' => $query]);
        return $count;
    }

    /**
     *
     * Create an Index
     *
     * @param  array  $index
     * @param  array  $options
     * @throws DatabaseException
     *
     */
    protected function addIndex(array $index, array $options = []): void
    {
        try {
            $this->active_collection->createIndex($index, $options);
        } catch (Exception $e) {
            throw new DatabaseException('0500: ' . $e->getMessage(), 0500);
        }
    }

    /**
     *
     * Create many indexes
     *
     * @param  array  $indexes
     * @param  array  $options
     * @throws DatabaseException
     *
     */
    protected function addIndexes(array $indexes, array $options = []): void
    {
        try {
            $this->active_collection->createIndexes($indexes, $options);
        } catch (Exception $e) {
            throw new DatabaseException('0500: ' . $e->getMessage(), 0500);
        }
    }

    /**
     *
     * Delete an index
     *
     * @param  string  $index
     * @throws DatabaseException
     *
     */
    protected function dropIndex(string $index): void
    {
        try {
            $this->active_collection->dropIndex($index);
        } catch (Exception $e) {
            throw new DatabaseException('0500: ' . $e->getMessage(), 0500);
        }
    }

    /**
     *
     * Delete indexes
     *
     * @param  array  $indexes
     * @throws DatabaseException
     *
     */
    protected function dropIndexes(array $indexes): void
    {
        try {
            $this->active_collection->dropIndexes($indexes);
        } catch (Exception $e) {
            throw new DatabaseException('0500: ' . $e->getMessage(), 0500);
        }
    }

    /**
     *
     * Clear current stack of operation data
     *
     */
    private function clearOps(): void
    {
        $this->currentProjection = [];
        $this->currentQuery = [];
        $this->currentSort = [];
        $this->currentPopulation = [];
        $this->currentOp = '';
        $this->currentField = '';
        $this->currentSkip = 0;
        $this->currentLimit = 10_000;
        $this->currentShowAll = false;
        $this->isSingle = false;
    }

    /**
     *
     * Handle populate feature (with subpopulation)
     *
     * @param  Model|null  $doc
     * @param  array       $populate
     * @return Model|null
     *
     */
    private static function parsePopulate(?Model $doc, array $populate): ?Model
    {
        if (!$doc) {
            return $doc;
        }

        $instance = new $populate['class']();
        $field = $populate['field'];
        $target = $populate['targetField'];
        $subpop = $populate['subpopulates'];

        $isArray = false;
        $isObject = false;
        $isCollection = false;
        $hasSub = false;
        $sub = '';

        if (str_contains($field, '.')) {
            [$field, $sub] = explode('.', $field);
            $hasSub = true;
        }

        if ($hasSub && isset($doc->{$field})) {
            if (is_array($doc->{$field})) {
                $isArray = true;
                $fieldValue = $doc->{$field};
            } elseif (is_object($doc->{$field}) && get_class($doc->{$field}) === Collection::class) {
                // handle collection
                $isArray = true;
                $isCollection = true;
                $fieldValue = $doc->{$field}->unwrap();
            } elseif (is_object($doc->{$field}) && get_class($doc->{$field}) === BSONArray::class) {
                // Handle BSONArray
                $isArray = true;
                $fieldValue = $doc->{$field}->bsonSerialize();
            } elseif (is_object($doc->{$field})) {
                // Handle object
                $isObject = true;
                $fieldValue = $doc->{$field};
            }
        }

        if ($isArray) {
            // Process a field within an array (every object gets that property) with support for subpopulation
            foreach ($fieldValue as $k => $v) {
                if (!is_object($v)) {
                    $obj = $instance->findById($v[$sub])->exec();
                } else {
                    $obj = $instance->findById($v->{$sub})->exec();
                }

                $v->{$target} = $obj;

                if (count($subpop) > 0) {
                    foreach ($subpop as $pop) {
                        $thepop = [
                            'field' => $pop[0],
                            'targetField' => $pop[1],
                            'class' => $pop[2],
                            'subpopulates' => $pop[3] ?? []
                        ];

                        if ($v->{$target}) {
                            $v->{$target}->{$pop[1]} = self::parsePopulate($v->{$target}, $thepop);
                        }
                    }
                }

                $fieldValue[$k] = $v;
            }

            if ($isCollection) {
                $doc->{$field} = new Collection($fieldValue);
            } else {
                $doc->{$field} = $fieldValue;
            }
        } elseif ($isObject) {
            // Process a field on an object (ex: $mod->obj->user_id to $mod->obj->user) with support for subpopulation.
            $obj = $instance->findById($fieldValue->{$sub})->exec();
            $doc->{$field}->{$target} = $obj;

            if (count($subpop) > 0) {
                foreach ($subpop as $pop) {
                    $thepop = [
                        'field' => $pop[0],
                        'targetField' => $pop[1],
                        'class' => $pop[2],
                        'subpopulates' => $pop[3] ?? []
                    ];

                    if ($doc->{$field}->{$target}) {
                        $doc->{$field}->{$target}->{$pop[1]} = self::parsePopulate($doc->{$field}->{$target}, $thepop);
                    }
                }
            }
        } elseif (is_object($doc->{$field}) && get_class($doc->{$field}) === Collection::class) {
            // handle collections ids
            $childs = [];
            foreach ($doc->{$field}->castFrom() as $k => $v) {
                $obj = $instance->findById($v)->exec();
                $childs[] = $obj;
            }
            $doc->{$target} = new Collection($childs);
        } else {
            // Process a field on a simple model (ex: $mod->user_id to $mod->user) with support for subpopulation.
            if ($doc->{$field}) {
                $obj = $instance->findById($doc->{$field})->exec();
                $doc->{$target} = $obj;

                if (count($subpop) > 0) {
                    foreach ($subpop as $pop) {
                        $thepop = [
                            'field' => $pop[0],
                            'targetField' => $pop[1],
                            'class' => $pop[2],
                            'subpopulates' => $pop[3] ?? []
                        ];

                        if ($doc->{$target}) {
                            $subDoc = self::parsePopulate($doc->{$target}, $thepop);
                            $doc->{$target}->{$pop[1]} = $subDoc->{$thepop['targetField']};
                        }
                    }
                }
            } else {
                $doc->{$target} = null;
            }
        }

        return $doc;
    }

    private function dumpPopulate(array $populate, string $parent = null): string
    {
        $dump = '';

        $class = $parent ? $populate[2] : $populate['class'];
        $instance = new $class();
        $collection = $instance->getCollection();
        $field = $parent ? $populate[0] : $populate['field'];
        $target = $parent ? $parent . '.' . $populate[1] : $populate['targetField'];
        $subpop = $parent ? ($populate[3] ?? []) : $populate['subpopulates'];

        $let = $parent ? "'let': {'$field': {'\$toObjectId': '\$$parent.$field'} }" : "'let': {'$field': {'\$toObjectId': '\$$field'} }";

        // Column with several elements
        if (str_contains($field, '.')) {
            $fields = explode('.', $field);
            $varfield = implode('_', $fields);
            $dump .= "
                { '\$addFields': { '$fields[0]': { '\$ifNull': ['\$$fields[0]', []] } } },
                {'\$unwind': {'path': '\$$fields[0]', 'preserveNullAndEmptyArrays': true}},
            ";
            $let = "'let': {'$varfield': {'\$toObjectId': '\$$field'} }";
            $field = $varfield;
        }

        $dump .= "{
            '\$lookup': {
                'from':'$collection',
                $let,
                'pipeline': [
                    {
                        '\$match': {
                            '\$expr': {
                                '\$eq': ['\$_id', '\$\$$field']
                            }
                        }
                    }
                ],
                'as': '$target'
            }
        },
        { '\$addFields': { '$target': { '\$ifNull': ['\$$target', []] } } },
        { '\$unwind': {'path': '\$$target', 'preserveNullAndEmptyArrays': true} }";

        foreach ($subpop as $pop) {
            $dump .= "," . $this->dumpPopulate($pop, $target);
        }

        return $dump;
    }
}
