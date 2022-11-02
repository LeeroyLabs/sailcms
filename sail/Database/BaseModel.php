<?php

namespace SailCMS\Database;

use JsonException;
use MongoDB\Model\BSONArray;
use SailCMS\Contracts\DatabaseType;
use SailCMS\Text;
use SailCMS\Errors\DatabaseException;
use SailCMS\Types\QueryOptions;
use \Carbon\Carbon;
use \MongoDB\BSON\ObjectId;
use \MongoDB\BSON\UTCDateTime;
use \MongoDB\Collection;
use stdClass;

abstract class BaseModel
{
    public const SORT_ASC = 1;
    public const SORT_DESC = -1;

    public ObjectId $_id;

    private Collection $collection;

    // Query operation Data
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

    public function __construct(string $collection = '')
    {
        // Manual name or detected by class name (plural)
        $name = array_reverse(explode('\\', get_class($this)))[0];

        $collection = (empty($collection)) ? Text::snakeCase(Text::inflector()->pluralize($name)[0]) : $collection;
        $client = Database::instance();

        $this->collection = $client->selectCollection($_ENV['DATABASE_DB'], $collection);
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
     * Make a value safe for querying. You should never query using a value
     * that is not either a string or number, unless you are sure that it's safe.
     *
     * @param  mixed  $value
     * @return string|int|bool|float
     * @throws JsonException
     *
     */
    protected function safe(mixed $value): string|int|bool|float
    {
        if (!is_string($value) && !is_numeric($value)) {
            return json_encode($value, JSON_THROW_ON_ERROR);
        }

        return $value;
    }

    /**
     *
     * Execute a query call (cannot be run with a mutation type method)
     *
     * @param  bool  $fetchAllFields
     * @return array|$this|null
     * @throws DatabaseException
     *
     */
    protected function exec(bool $fetchAllFields = false): BaseModel|array|null
    {
        $options = [];
        $docs = [];

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
                $doc = $this->transformDocToModel($result, $fetchAllFields);

                // Run all population requests
                foreach ($this->currentPopulation as $populate) {
                    $instance = new $populate['class']();
                    $field = $populate['field'];
                    $doc->{$field} = $instance->findById($doc->{$field})->exec();
                }

                $this->clearOps();
                return $doc;
            }

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
        } catch (\Exception $e) {
            throw new DatabaseException($e->getMessage(), 500);
        }

        foreach ($results as $result) {
            $doc = $this->transformDocToModel($result, $fetchAllFields);

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
    protected function populate(string $field, string $target, string $model): BaseModel
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
    protected function findById(string|ObjectId $id, QueryOptions|null $options = null): BaseModel
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
    protected function find(array $query, QueryOptions|null $options = null): BaseModel
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
    protected function findOne(array $query, QueryOptions|null $options = null): BaseModel
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
    protected function distinct(string $field, array $query, QueryOptions|null $options = null): BaseModel
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
        try {
            $results = $this->collection->aggregate($pipeline);
            $docs = [];

            foreach ($results as $result) {
                $docs[] = $this->transformDocToModel($result);
            }

            return $docs;
        } catch (\Exception $e) {
            throw new DatabaseException($e->getMessage(), 500);
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
        try {
            $doc = $this->prepareForWrite($doc);
            return $this->collection->insertOne($doc)->getInsertedId();
        } catch (\Exception $e) {
            throw new DatabaseException($e->getMessage(), 500);
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
        try {
            foreach ($docs as $num => $doc) {
                $docs[$num] = $this->prepareForWrite($doc);
            }

            return $this->collection->insertMany($docs)->getInsertedIds();
        } catch (\Exception $e) {
            throw new DatabaseException($e->getMessage(), 500);
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
        try {
            if (isset($update['$set'])) {
                $update['$set'] = $this->prepareForWrite($update['$set']);
            }

            return $this->collection->updateOne($query, $update)->getModifiedCount();
        } catch (\Exception $e) {
            throw new DatabaseException($e->getMessage(), 500);
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
        try {
            if (isset($update['$set'])) {
                $update['$set'] = $this->prepareForWrite($update['$set']);
            }

            return $this->collection->updateMany($query, $update)->getModifiedCount();
        } catch (\Exception $e) {
            throw new DatabaseException($e->getMessage(), 500);
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
        try {
            return $this->collection->deleteOne($query)->getDeletedCount();
        } catch (\Exception $e) {
            throw new DatabaseException($e->getMessage(), 500);
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
        try {
            return $this->collection->deleteMany($query)->getDeletedCount();
        } catch (\Exception $e) {
            throw new DatabaseException($e->getMessage(), 500);
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
        $_id = $id;

        if (is_string($id)) {
            $_id = new ObjectId($id);
        }

        try {
            return $this->collection->deleteOne(['_id' => $_id])->getDeletedCount();
        } catch (\Exception $e) {
            throw new DatabaseException($e->getMessage(), 500);
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
        return $this->collection->countDocuments($query);
    }

    /**
     *
     * Create an Index
     *
     * @param  array  $index
     * @throws DatabaseException
     *
     */
    protected function addIndex(array $index): void
    {
        try {
            $this->collection->createIndex($index);
        } catch (\Exception $e) {
            throw new DatabaseException($e->getMessage(), 500);
        }
    }

    /**
     *
     * Create many indexes
     *
     * @param  array  $indexes
     * @throws DatabaseException
     *
     */
    protected function addIndexes(array $indexes): void
    {
        try {
            $this->collection->createIndexes($indexes);
        } catch (\Exception $e) {
            throw new DatabaseException($e->getMessage(), 500);
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
        } catch (\Exception $e) {
            throw new DatabaseException($e->getMessage(), 500);
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
        } catch (\Exception $e) {
            throw new DatabaseException($e->getMessage(), 500);
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
            } else {
                if (is_object($this->{$field}) || is_array($this->{$field})) {
                    $doc[$field] = $this->simplifyEntity($this->{$field});
                } else {
                    $doc[$field] = $this->{$field};
                }
            }
        }

        try {
            return json_encode($doc, JSON_THROW_ON_ERROR | JSON_FORCE_OBJECT | JSON_PRETTY_PRINT);
        } catch (JsonException $e) {
            return "{}";
        }
    }

    /**
     *
     * Transform data to php stdClass
     *
     * @return stdClass
     *
     */
    public function toPHPObject(): \stdClass
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
    protected function timeToDate(int|float $time)
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

            if (is_object($entity) && isset($impl[DatabaseType::class])) {
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
}