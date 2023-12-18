<?php

namespace SailCMS\Database\Traits;

use Carbon\Carbon;
use Exception;
use JsonException;
use League\Flysystem\FilesystemException;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Model\BSONArray;
use MongoDB\Model\BSONDocument;
use SailCMS\Collection;
use SailCMS\Contracts\Castable;
use SailCMS\Database\Model;
use SailCMS\Security;
use stdClass;

trait Transforms
{
    use Casting;

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
        $guards = $this->guards;

        // Cancel guards in array situation
        if ($toArray) {
            $guards = [];
        }

        foreach ($this->properties as $key => $value) {
            if (!in_array($key, $guards, true) && (in_array($key, $this->loaded, true) || in_array('*', $this->loaded, true))) {
                if ($key === '_id') {
                    $doc[$key] = (string)$value;
                } elseif (!is_scalar($value)) {
                    $doc[$key] = $this->simplifyObject($value, $key);
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
     * Transform mongodb objects to clean php objects
     *
     * @param  array|object  $doc
     * @return Transforms|Model
     *
     */
    private function transformDocToModel(array|object $doc): self
    {
        return $this->runCasting($doc);
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
     * @throws \JsonException
     *
     */
    private function simplifyObject(mixed $obj, string $key = ''): mixed
    {
        // Handle scalar
        if (is_scalar($obj)) {
            return $obj;
        }

        if ($obj === null) {
            return null;
        }

        // Add Timestamp casting support
        if (!empty($key) && !empty($this->casting[$key]) && $this->casting[$key] === 'timestamp') {
            return $obj->getTimestamp();
        }

        // Process Collection first, because it will pass the is_array test
        if (is_object($obj) && get_class($obj) === Collection::class) {
            return $obj->unwrap();
        }

        // Handle array
        if (is_array($obj)) {
            foreach ($obj as $num => $item) {
                $obj[$num] = $this->simplifyObject($item);
            }

            return $obj;
        }

        // stdClass => stdClass
        if ($obj instanceof stdClass) {
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

        // Change the condition to avoid php_stan error.
        if (is_a($obj, Castable::class, true)) {
            return $this->simplifyObject($obj->castFrom());
        }

        // Give up
        return $obj;
    }

    /**
     *
     * Prepare document to be written
     *
     * @param  mixed  $doc
     * @return array
     * @throws Exception
     *
     */
    private function prepareForWrite(mixed $doc): array
    {
        $instance = new static();

        foreach ($doc as $key => $value) {
            $instance->properties[$key] = $value;
        }

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
}