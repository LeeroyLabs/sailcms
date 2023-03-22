<?php

namespace SailCMS\Database\Traits;

use Carbon\Carbon;
use Exception;
use \JsonException;
use League\Flysystem\FilesystemException;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Model\BSONArray;
use MongoDB\Model\BSONDocument;
use SailCMS\Collection;
use SailCMS\Contracts\Castable;
use SailCMS\Security;
use stdClass;

trait Transforms
{
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
            if (!in_array($key, $guards, true) && (in_array($key, $this->fields, true) || in_array('*', $this->fields, true))) {
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
     * Transform mongodb objects to clean php objects
     *
     * @param  array|object  $doc
     * @return static
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
                        // If an array and we are casting into Collection<Type>
                        if (is_array($cast)) {
                            if ($cast[0] === Collection::class) {
                                $list = [];

                                foreach ($v->bsonSerialize() as $_value) {
                                    $casted = new $cast[1]();

                                    if (is_object($_value) && get_class($_value) === BSONDocument::class) {
                                        foreach ($_value as $_k => $_v) {
                                            if (is_object($_v) && get_class($_v) === BSONArray::class) {
                                                $_value->{$_k} = $_v->bsonSerialize();
                                            } else {
                                                $_value->{$_k} = $_v;
                                            }
                                        }
                                    }

                                    $list[] = $casted->castTo($_value);
                                }

                                $castInstance = new $cast[0]($list);
                                $instance->{$k} = $castInstance;
                            }
                        } elseif ($cast === Collection::class) {
                            $castInstance = new $cast();
                            $instance->{$k} = $castInstance->castTo($v->bsonSerialize());
                        } elseif ($cast !== '') {
                            if (defined("$cast::HANDLE_ARRAY_CASTING") && $cast::HANDLE_ARRAY_CASTING) {
                                $casted = new $cast();
                                $instance->{$k} = $casted->castTo($v->bsonSerialize());
                            } else {
                                $list = [];


                                foreach ($v->bsonSerialize() as $_value) {
                                    $castInstance = new $cast();
                                    $list[] = $castInstance->castTo($_value);
                                }

                                $instance->{$k} = $list;
                            }
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
     * @throws \JsonException
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
        // if (isset(class_implements($obj)[Castable::class])
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