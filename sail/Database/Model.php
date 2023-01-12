<?php

namespace SailCMS\Database;

use Carbon\Carbon;
use Exception;
use \JsonException;
use JsonSerializable;
use League\Flysystem\FilesystemException;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\BulkWriteResult;
use MongoDB\Collection;
use MongoDB\Model\BSONArray;
use MongoDB\Model\BSONDocument;
use SailCMS\ACL;
use SailCMS\Cache;
use SailCMS\Contracts\Castable;
use SailCMS\Debug;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\PermissionException;
use SailCMS\Models\User;
use SailCMS\Security;
use SailCMS\Text;
use SailCMS\Types\QueryOptions;
use stdClass;

/**
 *
 * @property ObjectId $_id
 * @property string   $id
 *
 */
abstract class Model implements JsonSerializable
{
    // Connection and Collection
    protected int $connection = 0;
    protected string $collection = '';

    // Fields and Guards
    protected array $fields = ['*'];
    protected array $guards = [];

    // Automatic Casting of properties 
    protected array $casting = [];

    // Internal properties
    protected array $properties = [];

    // Permission group for the permission checks
    protected string $permissionGroup = '';

    protected array $validators = [];

    // Sorting
    public const SORT_ASC = 1;
    public const SORT_DESC = -1;

    // The collection object
    private Collection $active_collection;

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
     * @throws DatabaseException
     *
     */
    public function __construct()
    {
        // Just in case
        Cache::init();

        if ($this->collection === '') {
            // Setup using name of class
            $name = array_reverse(explode('\\', get_class($this)))[0];
            $name = Text::snakeCase(Text::pluralize($name));
            $this->collection = $name;
        }

        // Connection to use
        $client = Database::instance($this->connection);

        if (!$client) {
            throw new DatabaseException('Cannot connect to database, please check your DSN.', 0500);
        }

        $this->active_collection = $client->selectCollection(env('database_db', 'sailcms'), $this->collection);
        $this->init();
    }

    /**
     *
     * Create an instance without doing the classic instance first
     *
     * @return static
     *
     */
    public static function query(): static
    {
        return new static();
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
     * Get a property dynamically
     *
     * @param  string  $name
     * @return mixed|string|null
     *
     */
    public function __get(string $name)
    {
        if ($name === 'id') {
            return (string)$this->properties['_id'];
        }

        return $this->properties[$name] ?? null;
    }

    /**
     *
     * Set a properties value
     *
     * @param  string  $name
     * @param          $value
     * @return void
     *
     */
    public function __set(string $name, $value): void
    {
        if ($name !== 'id') {
            $this->properties[$name] = $value;
        }
    }

    /**
     *
     * Check if a property is set
     *
     * @param  string  $name
     * @return bool
     *
     */
    public function __isset(string $name): bool
    {
        return isset($this->properties[$name]);
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

    /**
     *
     * Execute a query call (cannot be run with a mutation type method)
     *
     * @param  string  $cacheKey
     * @param  int     $cacheTTL
     * @return array|static|null
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

        // Single query
        if ($this->isSingle) {
            $result = call_user_func([$this->active_collection, $this->currentOp], $this->currentQuery, $options);

            if ($result) {
                $doc = $this->transformDocToModel($result);

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
                    $this->active_collection,
                    $this->currentOp
                ], $this->currentField, $this->currentQuery, $options);
            } else {
                $results = call_user_func([$this->active_collection, $this->currentOp], $this->currentQuery, $options);
            }
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
        $this->currentCollation = $options->collation;
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
     * @throws FilesystemException
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
            return $id;
        } catch (Exception $e) {
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
            return $ids;
        } catch (Exception $e) {
            throw new DatabaseException('0500' . $e->getMessage(), 0500);
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
            return $res;
        } catch (Exception $e) {
            throw new DatabaseException('0500' . $e->getMessage(), 0500);
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
            return $count;
        } catch (Exception $e) {
            throw new DatabaseException('0500' . $e->getMessage(), 0500);
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
            return $count;
        } catch (Exception $e) {
            throw new DatabaseException('0500' . $e->getMessage(), 0500);
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
            $count = $this->active_collection->deleteOne($query)->getDeletedCount();

            $this->clearCacheForModel();
            $this->currentLimit = 1;
            $this->debugCall('deleteOne', $qt, ['query' => $query]);
            return $count;
        } catch (Exception $e) {
            throw new DatabaseException('0500' . $e->getMessage(), 0500);
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
            return $count;
        } catch (Exception $e) {
            throw new DatabaseException('0500' . $e->getMessage(), 0500);
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
            return $count;
        } catch (Exception $e) {
            throw new DatabaseException('0500' . $e->getMessage(), 0500);
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
            throw new DatabaseException('0500' . $e->getMessage(), 0500);
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
            throw new DatabaseException('0500' . $e->getMessage(), 0500);
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
            throw new DatabaseException('0500' . $e->getMessage(), 0500);
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
            throw new DatabaseException('0500' . $e->getMessage(), 0500);
        }
    }

    /**
     *
     * Handle the toString transformation
     *
     * @param  bool  $toArray
     * @return string|array
     * @throws JsonException
     *
     */
    public function toJSON(bool $toArray = false): string|array
    {
        $doc = [];

        foreach ($this->properties as $key => $value) {
            if (!in_array($key, $this->guards, true) && (in_array($key, $this->fields, true) || in_array('*', $this->fields, true))) {
                if ($key === '_id') {
                    $doc[$key] = (string)$value;
                } elseif (!is_scalar($value)) {
                    $doc[$key] = $this->simplifyObject($value);
                } else {
                    $doc[$key] = $value;
                }
            }
        }

        if ($toArray) {
            return $doc;
        }

        try {
            return json_encode($doc, JSON_THROW_ON_ERROR);
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
        return $this->toJSON(true);
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
            if (!ACL::hasPermission(User::$currentUser, ACL::read($this->permissionGroup))) {
                throw new PermissionException('0403: ' . $errorMsg, 0403);
            }
        } elseif (!ACL::hasPermission(User::$currentUser, ACL::write($this->permissionGroup))) {
            throw new PermissionException('0403: ' . $errorMsg, 0403);
        }
    }

    /**
     *
     * Clear all cache keys for the model
     *
     * @return void
     *
     */
    public function clearCacheForModel(): void
    {
        Cache::removeUsingPrefix(Text::snakeCase(get_class($this)));
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
     * @param  array|object  $doc
     * @return Model
     *
     */
    private function transformDocToModel(array|object $doc): self
    {
        $instance = new static();

        foreach ($doc as $k => $v) {
            $cast = $this->casting[$k] ?? '';

            if (is_object($v)) {
                $type = get_class($v);

                switch ($type) {
                    // Mongo Date
                    case UTCDateTime::class:
                        if ($cast === Carbon::class) {
                            $instance->{$k} = new Carbon($v->toDateTime());
                        } elseif ($cast === \DateTime::class) {
                            $instance->{$k} = $v->toDateTime();
                        } else {
                            $instance->{$k} = $v;
                        }
                        break;

                    case ObjectId::class:
                        if ($cast === ObjectId::class) {
                            $instance->{$k} = $v;
                        } elseif ($cast === 'string') {
                            $instance->{$k} = (string)$v;
                        } else {
                            $instance->{$k} = $v;
                        }
                        break;

                    case BSONArray::class:
                        if ($cast === \SailCMS\Collection::class) {
                            $castInstance = new $cast();
                            $instance->{$k} = $castInstance->castTo($v->bsonSerialize());
                        } else {
                            $instance->{$k} = $v->bsonSerialize();
                        }
                        break;

                    default:
                        if ($cast !== '') {
                            $castInstance = new $cast();

                            if (get_class($v) === BSONDocument::class) {
                                $v = $this->bsonToPHP($v);
                            }

                            $instance->{$k} = $castInstance->castTo($v);
                        } else {
                            $instance->{$k} = $v;
                        }
                        break;
                }
            } elseif (is_array($v)) {
                if ($cast !== '') {
                    $castInstance = new $cast();
                    $instance->{$k} = $castInstance->castTo($v);
                } else {
                    $instance->{$k} = $v;
                }
            } elseif (is_int($v) && $cast === \DateTime::class) {
                $castInstance = new \DateTime();
                $castInstance->setTimestamp($v);
                $instance->{$k} = $castInstance;
            } elseif (is_string($v) && $cast === 'encrypted') {
                try {
                    $instance->{$k} = Security::decrypt($v);
                } catch (FilesystemException|\SodiumException $e) {
                    // Unable to decrypt, return original
                    $instance->{$k} = $v;
                }
            } else {
                $instance->{$k} = $v;
            }
        }

        return $instance;
    }

    /**
     *
     * Process BSONDocument to basic php document
     *
     * @param  BSONDocument  $doc
     * @return stdClass
     *
     */
    private function bsonToPHP(BSONDocument $doc): stdClass
    {
        $newDoc = new stdClass();

        foreach ($doc as $key => $value) {
            if (is_object($value)) {
                $class = get_class($value);

                $newDoc->{$key} = match ($class) {
                    BSONArray::class => $value->bsonSerialize(),
                    BSONDocument::class => $this->bsonToPHP($value),
                    default => $value,
                };
            } else {
                $newDoc->{$key} = $value;
            }
        }

        return $newDoc;
    }

    /**
     *
     * Simplify an object to json compatible values
     *
     * @param  mixed  $obj
     * @return mixed
     * @throws JsonException
     *
     */
    private function simplifyObject(mixed $obj): mixed
    {
        // Handle scalar
        if (is_scalar($obj)) {
            return $obj;
        }

        if ($obj === null) {
            return null;
        }

        // Handle array
        if (is_array($obj)) {
            foreach ($obj as $num => $item) {
                $obj[$num] = $this->simplifyObject($item);
            }

            return $obj;
        }

        // stdClass => stdClass
        if (get_class($obj) === 'stdClass') {
            return $obj;
        }

        // Carbon => UTCDateTime
        if ($obj instanceof Carbon) {
            return new UTCDateTime($obj->toDateTime()->getTimestamp() * 1000);
        }

        // DateTime => UTCDateTime
        if ($obj instanceof \DateTime) {
            return new UTCDateTime($obj->getTimestamp() * 1000);
        }

        // ObjectID => String
        if ($obj instanceof ObjectId) {
            return (string)$obj;
        }

        $impl = class_implements($obj);

        if (isset($impl[Castable::class])) {
            return $this->simplifyObject($obj->castFrom());
        }

        // Give up
        return $obj;
    }

    /**
     *
     * Prepare document to be written
     *
     * @param  array  $doc
     * @return stdClass|array
     * @throws JsonException
     *
     */
    private function prepareForWrite(mixed $doc): stdClass|array
    {
        $instance = new static();
        $instance->fill($doc);
        $obj = $instance->toJSON(true);

        // Run the casting for encryption
        foreach ($obj as $key => $value) {
            if (isset($this->casting[$key]) && $this->casting[$key] === 'encrypted') {
                try {
                    $obj[$key] = Security::encrypt($value);
                } catch (FilesystemException|Exception $e) {
                    $obj[$key] = $value;
                }
            }
        }

        return $obj;
    }

    /**
     *
     * Fill an instance with the give object or array of data
     *
     * @param  mixed  $doc
     * @return $this
     *
     */
    protected function fill(mixed $doc): self
    {
        foreach ($doc as $key => $value) {
            $this->properties[$key] = $value;
        }

        return $this;
    }

    /**
     *
     * Run Model Validators
     *
     * @param $doc
     * @return void
     * @throws DatabaseException
     *
     */
    private function runValidators($doc): void
    {
        foreach ($this->validators as $key => $validator) {
            if (!str_contains($validator, '::')) {
                $subValidators = explode(',', $validator);

                foreach ($subValidators as $subValidator) {
                    switch ($validator) {
                        case 'not-empty':
                            if (empty($doc->{$key})) {
                                throw new DatabaseException("Property {$key} does pass validation, it should not be empty.", 0400);
                            }
                            break;

                        case 'string':
                            if (!is_string($doc->{$key})) {
                                $type = gettype($doc->{$key});
                                throw new DatabaseException("Property {$key} does pass validation, it should be a string but is a {$type}.", 0400);
                            }
                            break;

                        case 'numeric':
                            if (!is_numeric($doc->{$key})) {
                                $type = gettype($doc->{$key});
                                throw new DatabaseException("Property {$key} does pass validation, it should be a number but is a {$type}.", 0400);
                            }
                            break;

                        case 'boolean':
                            if (!is_bool($doc->{$key})) {
                                $type = gettype($doc->{$key});
                                throw new DatabaseException("Property {$key} does pass validation, it should be a boolean but is a {$type}.", 0400);
                            }
                            break;
                    }
                }
            } else {
                // Custom validator
                $impl = class_implements($validator);

                if (isset($impl[Validator::class])) {
                    call_user_func([$validator, 'validate'], $key, $doc->{$key});
                } else {
                    throw new DatabaseException("Cannot use {$validator} to validate {$key} because it does not implement the Validator Interface", 400);
                }
            }
        }
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
            'collection' => $this->active_collection->getCollectionName(),
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
}