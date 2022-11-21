<?php

namespace SailCMS\Database;

use Carbon\Carbon;
use Exception;
use JsonException;
use JsonSerializable;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\BulkWriteResult;
use MongoDB\Collection;
use MongoDB\Model\BSONArray;
use SailCMS\ACL;
use SailCMS\Cache;
use SailCMS\Contracts\DatabaseType;
use SailCMS\Debug;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\PermissionException;
use SailCMS\Models\User;
use SailCMS\Text;
use SailCMS\Types\QueryOptions;
use stdClass;

abstract class Model implements JsonSerializable
{
    public const SORT_ASC = 1;
    public const SORT_DESC = -1;

    public ObjectId $_id;

    private Collection $collection;

    private string $_permissionGroup = '';

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
    private bool $isSingle = false;

    abstract public function fields(bool $fetchAllFields = false): array;

    /**
     * @throws DatabaseException
     */
    public function __construct(string $collection = '', int $dbIndex = 0)
    {
        // Just in case
        Cache::init();

        // Manual name or detected by class name (plural)
        $name = array_reverse(explode('\\', get_class($this)))[0];

        if (empty($collection)) {
            $preformatted = explode(' ', str_replace('_', ' ', Text::snakeCase($name)));
            $preformatted[count($preformatted) - 1] = Text::inflector()->pluralize($preformatted[count($preformatted) - 1])[0];
            $name = implode('_', $preformatted);
        }

        $collection = ($collection === '') ? $name : $collection;
        $client = Database::instance($dbIndex);

        $this->collection = $client->selectCollection(env('database_db', 'sailcms'), $collection);

        $this->init();
    }

    /**
     *
     * Overridable function called on construct
     *
     * @return void
     *
     */
    public function init(): void
    {
        // implemented at model level
    }

    /**
     *
     * Make sure the value given is already an ObjectId or transform it to one
     *
     * @param  string|ObjectId  $id
     * @return ObjectId
     *
     */
    public function ensureObjectId(string|ObjectId $id): ObjectId
    {
        if (is_string($id)) {
            return new ObjectId($id);
        }

        return $id;
    }

    /**
     *
     * Set the group to look for in the model
     *
     * @param  string  $group
     * @return void
     *
     */
    public function setPermissionGroup(string $group): void
    {
        $this->_permissionGroup = $group;
    }

    /**
     *
     * Make a value safe for querying. You should never query using a value
     * that is not either a string or number, unless you are sure that it's safe.
     *
     * @param  mixed  $value
     * @return string|array|bool|int|float
     * @throws JsonException
     *
     */
    protected function safe(mixed $value): string|array|bool|int|float
    {
        if (is_scalar($value)) {
            return $value;
        }

        $output = [];

        foreach ($value as $k => $v) {
            $safe_k = str_replace('$', '', $k);
            $output[$safe_k] = $this->safe($v);
        }

        return $output;
    }

    public function allFields(): static
    {
        $this->currentShowAll = true;
        return $this;
    }

    /**
     *
     * Execute a query call (cannot be run with a mutation type method)
     *
     * @param  string  $cacheKey
     * @param  int     $cacheTTL
     * @return array|$this|null
     * @throws DatabaseException
     *
     */
    protected function exec(string $cacheKey = '', int $cacheTTL = Cache::TTL_WEEK): Model|array|null
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
                        $cached[$num] = $this->transformDocToModel($cache, false);
                    }
                } elseif (is_object($cached)) {
                    // CastBack
                    $cached = $this->transformDocToModel($cached, false);
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

        // Single query
        if ($this->isSingle) {
            $result = call_user_func([$this->collection, $this->currentOp], $this->currentQuery, $options);

            if ($result) {
                $doc = $this->transformDocToModel($result, $this->currentShowAll);

                // Run all population requests
                foreach ($this->currentPopulation as $populate) {
                    $instance = new $populate['class']();
                    $field = $populate['field'];
                    $doc->{$field} = $instance->findById($doc->{$field})->exec();
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
                $results = call_user_func([
                    $this->collection,
                    $this->currentOp
                ], $this->currentField, $this->currentQuery, $options);
            } else {
                $results = call_user_func([$this->collection, $this->currentOp], $this->currentQuery, $options);
            }
        } catch (Exception $e) {
            throw new DatabaseException($e->getMessage(), 0500);
        }

        foreach ($results as $result) {
            $doc = $this->transformDocToModel($result, $this->currentShowAll);

            // Run all population requests
            foreach ($this->currentPopulation as $populate) {
                $instance = new $populate['class']();
                $field = $populate['field'];
                $target = $populate['targetField'];

                // Make sure to run only if field is not null and not empty string
                if ($doc->{$field} !== null && $doc->{$field} !== '') {
                    $doc->{$target} = $instance->findById($doc->{$field})->exec();
                } else {
                    // Nullify the field, most probably going to be called from GraphQL
                    $doc->{$target} = null;
                }
            }

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
     * Auto-populate a field when it is fetched from the database
     * (must be an ObjectId or a string representation)
     *
     * @param  string  $field
     * @param  string  $target
     * @param  string  $model
     * @return $this
     *
     */
    protected function populate(string $field, string $target, string $model): Model
    {
        $this->currentPopulation[] = [
            'field' => $field,
            'targetField' => $target,
            'class' => $model
        ];

        return $this;
    }

    /**
     *
     * Find by id
     *
     * @param  string|ObjectId    $id
     * @param  QueryOptions|null  $options
     * @return $this
     *
     */
    protected function findById(string|ObjectId $id, QueryOptions|null $options = null): Model
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
     * @return $this
     *
     */
    protected function find(array $query, QueryOptions|null $options = null): Model
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
        return $this;
    }

    /**
     *
     * Find one
     *
     * @param  array              $query
     * @param  QueryOptions|null  $options
     * @return $this
     */
    protected function findOne(array $query, QueryOptions|null $options = null): Model
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
     * @return $this
     *
     */
    protected function distinct(string $field, array $query, QueryOptions|null $options = null): Model
    {
        if (!$options) {
            $options = QueryOptions::init();
        }

        $this->currentOp = 'distinct';
        $this->currentField = $field;
        $this->isSingle = true;
        $this->currentQuery = $query;
        $this->currentLimit = 1;
        $this->currentSort = $options->sort ?? [];
        $this->currentProjection = $options->projection ?? [];
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
            $results = $this->collection->aggregate($pipeline);
            $docs = [];

            foreach ($results as $result) {
                $docs[] = $this->transformDocToModel($result);
            }

            $this->debugCall('aggregate', $qt, ['pipeline' => $pipeline]);
            return $docs;
        } catch (Exception $e) {
            throw new DatabaseException($e->getMessage(), 0500);
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
            $id = $this->collection->insertOne($doc)->getInsertedId();

            $this->clearCacheForModel();
            $this->debugCall('insert', $qt);
            return $id;
        } catch (Exception $e) {
            throw new DatabaseException($e->getMessage(), 0500);
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
                $docs[$num] = $this->prepareForWrite($doc);
            }

            $ids = $this->collection->insertMany($docs)->getInsertedIds();

            $this->clearCacheForModel();
            $this->debugCall('insertMany', $qt);
            return $ids;
        } catch (Exception $e) {
            throw new DatabaseException($e->getMessage(), 0500);
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
            $res = $this->collection->bulkWrite($writes);

            $this->clearCacheForModel();
            $this->debugCall('bulkWrite', $qt);
            return $res;
        } catch (Exception $e) {
            throw new DatabaseException($e->getMessage(), 0500);
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
                $update['$set'] = $this->prepareForWrite($update['$set']);
            }

            $count = $this->collection->updateOne($query, $update)->getModifiedCount();

            $this->clearCacheForModel();
            $this->currentLimit = 1;
            $this->debugCall('updateOne', $qt, ['query' => $query, 'update' => $update]);
            return $count;
        } catch (Exception $e) {
            throw new DatabaseException($e->getMessage(), 0500);
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
                $update['$set'] = $this->prepareForWrite($update['$set']);
            }

            $count = $this->collection->updateMany($query, $update)->getModifiedCount();

            $this->clearCacheForModel();
            $this->debugCall('updateMany', $qt, ['query' => $query, 'update' => $update]);
            return $count;
        } catch (Exception $e) {
            throw new DatabaseException($e->getMessage(), 0500);
        }
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
            $count = $this->collection->deleteOne($query)->getDeletedCount();

            $this->clearCacheForModel();
            $this->currentLimit = 1;
            $this->debugCall('deleteOne', $qt, ['query' => $query]);
            return $count;
        } catch (Exception $e) {
            throw new DatabaseException($e->getMessage(), 0500);
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
            $count = $this->collection->deleteMany($query)->getDeletedCount();

            $this->clearCacheForModel();
            $this->debugCall('deleteMany', $qt, ['query' => $query]);
            return $count;
        } catch (Exception $e) {
            throw new DatabaseException($e->getMessage(), 0500);
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
        $_id = $id;

        if (is_string($id)) {
            $_id = new ObjectId($id);
        }

        try {
            $count = $this->collection->deleteOne(['_id' => $_id])->getDeletedCount();

            $this->clearCacheForModel();
            $this->debugCall('deleteById', $qt, ['query' => ['_id' => $_id]]);
            return $count;
        } catch (Exception $e) {
            throw new DatabaseException($e->getMessage(), 0500);
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
        $count = $this->collection->countDocuments($query);

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
            $this->collection->createIndex($index, $options);
        } catch (Exception $e) {
            throw new DatabaseException($e->getMessage(), 0500);
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
            $this->collection->createIndexes($indexes, $options);
        } catch (Exception $e) {
            throw new DatabaseException($e->getMessage(), 0500);
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
            $this->collection->dropIndex($index);
        } catch (Exception $e) {
            throw new DatabaseException($e->getMessage(), 0500);
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
            $this->collection->dropIndexes($indexes);
        } catch (Exception $e) {
            throw new DatabaseException($e->getMessage(), 0500);
        }
    }

    /**
     *
     * Handle the toString transformation
     *
     */
    public function toJSON(object $obj = null): string
    {
        $fields = $this->fields();
        $doc = [];

        foreach ($fields as $field) {
            if ($field === '_id') {
                $doc[$field] = (string)$this->{$field};
            } elseif (is_object($this->{$field}) || is_array($this->{$field})) {
                $doc[$field] = $this->simplifyEntity($this->{$field});
            } else {
                $doc[$field] = $this->{$field};
            }
        }

        try {
            return json_encode($doc, JSON_THROW_ON_ERROR | JSON_FORCE_OBJECT);
        } catch (JsonException $e) {
            return "{}";
        }
    }

    /**
     *
     * Support for json_encode triggering
     *
     * @return array
     *
     */
    public function jsonSerialize(): array
    {
        $fields = $this->fields();
        $doc = [];

        foreach ($fields as $field) {
            if ($field === '_id') {
                $doc[$field] = (string)$this->{$field};
            } elseif (is_object($this->{$field}) || is_array($this->{$field})) {
                $doc[$field] = $this->simplifyEntity($this->{$field});
            } else {
                $doc[$field] = $this->{$field};
            }
        }

        return $doc;
    }

    /**
     *
     * Transform data to php stdClass
     *
     * @return stdClass
     *
     */
    public function toPHPObject(): stdClass
    {
        $fields = $this->fields();
        $doc = [];

        foreach ($fields as $field) {
            if ($field === '_id') {
                $doc[$field] = (string)$this->{$field};
            } else {
                if (is_object($this->{$field}) || is_array($this->{$field})) {
                    $doc[$field] = $this->simplifyEntity($this->{$field});
                } else {
                    $doc[$field] = $this->{$field};
                }
            }
        }

        return (object)$doc;
    }

    /**
     *
     * Turn php timestamp (seconds) to a MongoDB compatible Date object
     *
     * @param  int|float  $time
     * @return UTCDateTime
     *
     */
    protected function timeToDate(int|float $time): UTCDateTime
    {
        return new UTCDateTime($time * 1000);
    }

    /**
     *
     * This should be overridden to apply specific changes to fields when they are fetched
     *
     * @param  string  $field
     * @param  mixed   $value
     * @return mixed
     *
     */
    protected function processOnFetch(string $field, mixed $value): mixed
    {
        return $value;
    }

    /**
     *
     * This should be overridden to apply specific changes to fields when they are being written to database
     *
     * @param  string  $field
     * @param  mixed   $value
     * @return mixed
     *
     */
    protected function processOnStore(string $field, mixed $value): mixed
    {
        return $value;
    }

    /**
     *
     * A reusable permission checker
     *
     * @param  bool  $read
     * @return void
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    protected function hasPermissions(bool $read = false): void
    {
        $errorMsg = 'Permission Denied (' . get_class($this) . ')';
        if ($read) {
            if (!ACL::hasPermission(User::$currentUser, ACL::read($this->_permissionGroup))) {
                throw new PermissionException($errorMsg, 0403);
            }
        } elseif (!ACL::hasPermission(User::$currentUser, ACL::write($this->_permissionGroup))) {
            throw new PermissionException($errorMsg, 0403);
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
    }

    /**
     *
     * Transform mongodb objects to clean php objects
     *
     * @param  object  $doc
     * @param  bool    $fetchAllFields
     * @return $this
     *
     */
    private function transformDocToModel(object $doc, bool $fetchAllFields = false): static
    {
        $instance = new static();
        $fields = $instance->fields($fetchAllFields);

        foreach ($doc as $k => $v) {
            // Only take what is declared in fields
            if (in_array($k, $fields, true)) {
                if (is_object($v)) {
                    if ($k !== '_id' && get_class($v) === BSONArray::class) {
                        $instance->{$k} = new \SailCMS\Collection($this->processOnFetch($k, $v->bsonSerialize()));
                    } elseif (get_class($v) === UTCDateTime::class) {
                        $instance->{$k} = $this->processOnFetch($k, new Carbon($v->toDateTime()));
                    } elseif (get_class($v) !== ObjectId::class) {
                        $instance->{$k} = $this->processOnFetch($k, $this->parseRegularObject($v));
                    } else {
                        $instance->{$k} = $this->processOnFetch($k, $v);
                    }
                } elseif (is_array($v)) {
                    $instance->{$k} = new \SailCMS\Collection($this->processOnFetch($k, $v));
                } elseif ($k === '_id' && is_string($v)) {
                    $instance->{$k} = new ObjectId($v);
                } else {
                    $instance->{$k} = $this->processOnFetch($k, $v);
                }
            }
        }

        return $instance;
    }

    /**
     *
     * Process regular variables recursively
     *
     * @param  object  $obj
     * @return stdClass
     *
     */
    private function parseRegularObject(object $obj): stdClass
    {
        $out = new stdClass;

        foreach ($obj as $k => $v) {
            if (is_object($v)) {
                if (get_class($v) === ObjectId::class) {
                    $out->{$k} = $this->processOnFetch($k, $v);
                } elseif (get_class($v) === BSONArray::class) {
                    $out->{$k} = $this->processOnFetch($k, $v->bsonSerialize());
                } else {
                    $out->{$k} = $this->processOnFetch($k, $this->parseRegularObject($v));
                }
            } else {
                $out->{$k} = $this->processOnFetch($k, $v);
            }
        }

        return $out;
    }

    /**
     *
     * Simplify a variable to be easily encoded to json
     *
     * @param  mixed  $entity
     * @return mixed
     *
     */
    private function simplifyEntity(mixed $entity): mixed
    {
        if (is_array($entity)) {
            foreach ($entity as $num => $item) {
                $entity[$num] = $this->simplifyEntity($item);
            }

            return $entity;
        }

        if (is_object($entity)) {
            if ($entity instanceof ObjectId) {
                return (string)$entity;
            }

            $impl = class_implements($entity);

            if (isset($impl[DatabaseType::class])) {
                return $entity->toDBObject();
            }

            foreach ($entity as $key => $value) {
                $entity->{$key} = $this->simplifyEntity($value);
            }

            return $entity;
        }

        return $entity;
    }

    /**
     *
     * Prepare document to be written, transform Carbon dates to MongoDB dates
     *
     * @param  array  $doc
     * @return stdClass|array
     */
    private function prepareForWrite(mixed $doc): stdClass|array
    {
        if (is_array($doc) || is_iterable($doc)) {
            foreach ($doc as $key => $value) {
                if ($value instanceof Carbon) {
                    $doc[$key] = $this->processOnStore($key, new UTCDateTime($value->toDateTime()->getTimestamp() * 1000));
                } elseif (is_scalar($value)) {
                    $doc[$key] = $this->processOnStore($key, $value);
                } elseif (is_object($value) && get_class($value) === \SailCMS\Collection::class) {
                    $doc[$key] = $value->unwrap();
                } elseif (is_array($value) || is_object($value)) {
                    $doc[$key] = $this->processOnStore($key, $this->prepareForWrite($value));
                } else {
                    $doc[$key] = $this->processOnStore($key, $value);
                }
            }
        } elseif (is_object($doc)) {
            $impl = class_implements(get_class($doc));

            if (isset($impl[DatabaseType::class])) {
                $doc = $doc->toDBObject();
            }
        }

        return $doc;
    }

    /**
     *
     * Debug the db call just called
     *
     * @param  string  $op
     * @param  float   $time
     * @param  array   $extra
     * @return void
     *
     */
    private function debugCall(string $op = '', float $time = 0, array $extra = []): void
    {
        $debugQuery = [
            'operation' => ($op === '') ? $this->currentOp : $op,
            'query' => $extra['query'] ?? $this->currentQuery,
            'projection' => $this->currentProjection ?? [],
            'sort' => $this->currentSort ?? [],
            'offset' => $this->currentSkip ?? 0,
            'limit' => $this->currentLimit ?? 10_000,
            'model' => get_class($this),
            'collection' => $this->collection->getCollectionName(),
            'time' => $time,
            'update' => $extra['update'] ?? [],
            'pipeline' => $extra['pipeline'] ?? []
        ];

        Debug::endQuery($debugQuery);
    }

    /**
     *
     * Build a cache key with the given name
     *
     * @param  string  $key
     * @return string
     *
     */
    private function assembleCacheKey(string $key): string
    {
        return Text::snakeCase(get_class($this)) . ':' . $key;
    }

    /**
     *
     * Clear all cache keys for the model
     *
     * @return void
     *
     */
    private function clearCacheForModel(): void
    {
        Cache::removeUsingPrefix(Text::snakeCase(get_class($this)));
    }
}