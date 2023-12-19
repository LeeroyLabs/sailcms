<?php

namespace SailCMS\Database\Traits;

use SailCMS\Collection;
use SailCMS\Database\Model;

trait Relationships
{
    protected const hasOne = 'has_one';
    protected const hasMany = 'has_many';
    protected const hasManyOn = 'has_many_on';

    protected int $currentDepth = 0;
    protected bool $skipRelation = false;

    private function fetchRelationships(Model $doc): self
    {
        if ($this->skipRelation) {
            return $doc;
        }

        $maxDepth = setting('database.max_relationship_resolve_depth', 3);

        // Stop infinite relationship resolving
        if ($this->currentDepth >= $maxDepth) {
            return $doc;
        }

        foreach ($this->relationships as $key => $relationship) {
            @[$type, $class, $newName] = $relationship;

            if ($type === self::hasOne && isset($doc->{$key})) {
                if (isset($newName) && $newName !== '') {
                    $doc->{$newName} = $this->fetchSingleRelation($doc->{$key}, $class);
                } else {
                    $doc->{$key} = $this->fetchSingleRelation($doc->{$key}, $class);
                }
            } elseif ($type === self::hasMany && isset($doc->{$key})) {
                if (isset($newName) && $newName !== '') {
                    $doc->{$newName} = $this->fetchManyRelation($doc->{$key}, $class);
                } else {
                    $doc->{$key} = $this->fetchManyRelation($doc->{$key}, $class);
                }
            } elseif ($type === self::hasManyOn) {
                $doc->{$key} = $this->fetchManyOnRelation($doc->_id, $class, $newName);
            }
        }

        return $doc;
    }

    protected function setRelationDepth(int $depth = 0): self
    {
        $this->currentDepth = $depth;
        return $this;
    }

    private function fetchSingleRelation(mixed $value, string $class): mixed
    {
        if ($this->isValidId($value)) {
            $instance = new $class();
            $instance->calledFromRelationCall = true;
            return $instance->findById($value)->setRelationDepth($this->currentDepth + 1)->exec();
        }

        return $value;
    }

    private function fetchManyRelation(mixed $value, string $class): mixed
    {
        $collectionFlag = false;

        if ($value instanceof Collection) {
            $value = $value->unwrap();
            $collectionFlag = true;
        }

        if (is_array($value)) {
            $instance = new $class();
            $instance->calledFromRelationCall = true;
            $value = $instance->find([
                '_id' => ['$in' => $this->ensureObjectIds($value, true)]
            ])->setRelationDepth($this->currentDepth + 1)->exec();
        }

        if ($collectionFlag) {
            return new Collection($value);
        }

        return $value;
    }

    private function fetchManyOnRelation(mixed $value, string $class, string $targetField = ''): mixed
    {
        $instance = new $class();
        $instance->calledFromRelationCall = true;

        $oid = $this->ensureObjectId($value);

        return $instance->find([
            '$or' => [
                [$targetField => (string)$value],
                [$targetField => $oid]
            ]
        ])->setRelationDepth($this->currentDepth + 1)->exec();
    }
}