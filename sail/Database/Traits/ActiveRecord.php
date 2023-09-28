<?php

namespace SailCMS\Database\Traits;

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;
use SailCMS\Errors\DatabaseException;

trait ActiveRecord
{
    // Dirty Handler
    protected bool $isDirty = false;
    protected bool $exists = false;

    protected array $dirtyFields = [];

    // on save tooling
    protected array $currentIncrements = [];
    protected array $currentPushes = [];
    protected array $currentPulls = [];
    protected array $currentPullAlls = [];
    protected array $currentPops = [];

    protected array $superseededProperties = [];

    /**
     *
     * Is this document dirty (been modified without being saved) ?
     *
     * @return bool
     *
     */
    public function isDirty(): bool
    {
        return $this->isDirty;
    }

    public function setDirty(string $field): void
    {
        $this->isDirty = true;
        $this->dirtyFields[] = $field;
    }

    /**
     *
     * Does this document exist in the database
     *
     * @return bool
     *
     */
    public function exists(): bool
    {
        return $this->exists;
    }

    /**
     *
     * Mark a field to be incremented on save
     *
     * @param  string     $field
     * @param  int|float  $by
     * @return $this
     *
     */
    public function increment(string $field, int|float $by): static
    {
        $this->currentIncrements[$field] = $by;
        $this->superseededProperties[] = $field;
        $this->isDirty = true;
        return $this;
    }

    /**
     *
     * Push a value at the end of an array
     *
     * @param  string  $field
     * @param  mixed   $value
     * @return $this
     *
     */
    public function push(string $field, mixed $value): static
    {
        $this->currentPushes[$field] = $value;
        $this->superseededProperties[] = $field;
        $this->isDirty = true;
        return $this;
    }

    /**
     *
     * Push many elements at once in an array
     * You can also sort the array afterwards and you can slice the array afterwards ($slice)
     *
     * @param  string  $field
     * @param  mixed   $values
     * @param  array   $sort
     * @param  int     $slice
     * @return $this
     *
     */
    public function pushEach(string $field, mixed $values, array $sort = [], int $slice = -1_000_000_000): static
    {
        $push = [];

        if (count($sort) > 0) {
            $push['$sort'] = $sort;
        }

        $push['$each'] = $values;

        if ($slice !== -1_000_000_000) {
            $push['$slice'] = $slice;
        }

        $this->currentPushes[$field] = $push;
        $this->superseededProperties[] = $field;
        $this->isDirty = true;
        return $this;
    }

    /**
     *
     * Pop first or last element of an array
     *
     * @param  string  $field
     * @param  bool    $first
     * @return $this
     *
     */
    public function pop(string $field, bool $first = false): static
    {
        $v = ($first) ? -1 : 1;
        $this->currentPops[$field] = $v;
        $this->superseededProperties[] = $field;
        $this->isDirty = true;
        return $this;
    }

    /**
     *
     * Pull one match from an array
     *
     * @param  string  $field
     * @param  mixed   $match
     * @return $this
     *
     */
    public function pull(string $field, mixed $match): static
    {
        $this->currentPulls[$field] = $match;
        $this->superseededProperties[] = $field;
        $this->isDirty = true;
        return $this;
    }

    /**
     *
     * Pull all matches from an array
     *
     * @param  string  $field
     * @param  mixed   $match
     * @return $this
     *
     */
    public function pullAll(string $field, mixed $match): static
    {
        $this->currentPullAlls[$field] = $match;
        $this->superseededProperties[] = $field;
        $this->isDirty = true;
        return $this;
    }

    /**
     *
     * ActiveRecord save
     *
     * @return bool
     * @throws DatabaseException
     * @throws \JsonException
     *
     */
    public function save(): bool
    {
        $saveWhole = setting('database.activerecord_save_whole_object', false);

        // Automatically dirty and everything in it
        if ($saveWhole) {
            $this->isDirty = true;

            foreach ($this->properties as $key => $value) {
                if ($key !== '_id') {
                    $this->dirtyFields[] = $key;
                }
            }
        }

        if ($this->isDirty) {
            $set = [];

            foreach ($this->properties as $key => $value) {
                if ($key !== '_id' && in_array($key, $this->dirtyFields, true) && !in_array($key, $this->superseededProperties, true)) {
                    $set[$key] = $value;
                }
            }

            if ($this->id === '') {
                try {
                    $id = $this->insert($set);
                    $this->properties['_id'] = $id;
                    $this->isDirty = false;
                    $this->exists = true;
                } catch (DatabaseException $e) {
                    return false;
                }
                return true;
            } else {
                $call = [];

                if (count($set) > 0) {
                    $set = json_decode(
                        json_encode($set, JSON_THROW_ON_ERROR),
                        true,
                        512,
                        JSON_THROW_ON_ERROR
                    );
                    $call = ['$set' => $set];
                }

                if (count($this->currentIncrements) > 0) {
                    $call['$inc'] = $this->currentIncrements;
                }

                if (count($this->currentPushes) > 0) {
                    $call['$push'] = $this->currentPushes;
                }

                if (count($this->currentPops) > 0) {
                    $call['$pop'] = $this->currentPops;
                }

                if (count($this->currentPulls) > 0) {
                    $call['$pull'] = $this->currentPulls;
                }

                if (count($this->currentPullAlls) > 0) {
                    $call['$pullAll'] = $this->currentPullAlls;
                }

                if (empty($call)) {
                    return false;
                }

                $saved = $this->updateOne(['_id' => $this->_id], $call);

                if ($saved > 0) {
                    $this->dirtyFields = [];
                    $this->currentIncrements = [];
                    $this->currentPushes = [];
                    $this->currentPulls = [];
                    $this->currentPullAlls = [];
                    $this->currentPops = [];
                    $this->superseededProperties = [];
                    $this->isDirty = false;
                    return true;
                }
            }
        }

        return false;
    }

    /**
     *
     * ActiveRecord remove document
     *
     * @return bool
     * @throws DatabaseException
     *
     */
    public function remove(): bool
    {
        if ($this->exists) {
            $deleted = $this->deleteById($this->id);
            $this->exists = false;
            return ($deleted > 0);
        }

        return false;
    }

    /**
     *
     * ActiveRecord get a record by id
     *
     * @param  string|ObjectId  $id
     * @return $this|null
     * @throws DatabaseException
     *
     */
    public static function get(string|ObjectId $id): ?static
    {
        $instance = self::query();
        return $instance->findOne(['_id' => $instance->ensureObjectId($id)])->exec();
    }

    /**
     *
     * ActiveRecord get record by given field/value and operator
     * Defaults to == operator
     *
     * @param  string  $field
     * @param  mixed   $value
     * @param  string  $operator
     * @return static|null
     * @throws DatabaseException
     *
     */
    public static function getBy(string $field, mixed $value, string $operator = '=='): ?static
    {
        $instance = self::query();

        switch ($operator) {
            default:
            case '==':
            case 'eq':
                return $instance->findOne([$field => $value])->exec();

            case '!=':
            case 'ne':
                return $instance->findOne([$field => ['$ne' => $value]])->exec();

            case '>':
            case 'gt':
                return $instance->findOne([$field => ['$gt' => $value]])->exec();

            case '<':
            case 'lt':
                return $instance->findOne([$field => ['$lt' => $value]])->exec();

            case '>=':
            case 'gte':
                return $instance->findOne([$field => ['$gte' => $value]])->exec();

            case '<=':
            case 'lte':
                return $instance->findOne([$field => ['$lte' => $value]])->exec();

            case 'in':
            case 'has':
                return $instance->findOne([$field => ['$in' => $value]])->exec();

            case 'notin':
            case 'nin':
                return $instance->findOne([$field => ['$nin' => $value]])->exec();

            case 'like':
            case 'regex':
                return $instance->findOne([$field => new Regex($value, 'gi')])->exec();

            case 'notlike':
                return $instance->findOne([$field => ['$not' => new Regex($value, 'gi')]])->exec();
        }
    }

    /**
     *
     * Refresh the properties of the object with what is in the database
     * This is useful when you use special methods like increment, pull, push, etc.
     * This only updates the fields that were dirty.
     *
     * @return void
     * @throws DatabaseException
     *
     */
    public function refresh(): void
    {
        $record = $this->findById($this->id)->exec();

        if ($record) {
            foreach ($this->properties as $prop => $value) {
                if (isset($record->{$prop})) {
                    $this->properties[$prop] = $record->{$prop};
                }
            }
        }
    }
}