<?php

namespace SailCMS\Database\Traits;

use Carbon\Carbon;
use League\Flysystem\FilesystemException;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Model\BSONArray;
use MongoDB\Model\BSONDocument;
use SailCMS\Collection;
use SailCMS\Security;
use stdClass;

trait Casting
{
    private function runCasting(array|object $doc): self
    {
        $instance = new static();

        if (is_array($doc)) {
            $doc = (object)$doc;
        }

        foreach ($doc as $key => $value) {
            if (!empty($this->casting[$key])) {
                $instance->{$key} = $this->processCast($value, $this->casting[$key]);
            } else {
                $instance->{$key} = $value;
            }
        }

        return $instance;
    }

    private function processCast(mixed $value, mixed $casting): mixed
    {
        $type = gettype($value);
        $unprocessed = ['string', 'boolean', 'integer', 'float', 'double', 'NULL'];
        $special = ['encrypted', 'timestamp', ObjectId::class];

        // Do not process any scalar value
        if (in_array($type, $unprocessed, true)) {
            if (!in_array($casting, $special, true)) {
                return $value;
            }
        }

        // Encrypted string
        if ($type === 'string' && $casting === 'encrypted') {
            return $this->decryptValue($value);
        }

        // Timestamp
        if ($type === 'integer' && $casting === 'timestamp') {
            return $this->timestampToDate($value);
        }

        // ObjectId
        if ($type === 'string' && $casting === ObjectId::class) {
            return $this->ensureObjectId($value);
        }

        if ($type === 'object') {
            return $this->castObject($value, $casting);
        }

        return $value;
    }

    private function castObject(mixed $value, mixed $casting): mixed
    {
        $class = get_class($value);

        switch ($class) {
            // Array
            case BSONArray::class:
                if (is_string($casting)) {
                    return new Collection($value->bsonSerialize());
                }

                return $this->castArray($value->bsonSerialize(), $casting);

            // Regular Object
            case BSONDocument::class:
                $vObj = $this->toRegularObject($value);
                return (new $casting())->castTo($vObj);

            // MongoDB DateTime
            case UTCDateTime::class:
                return $this->castDateTime($value, $casting);
        }

        return $value;
    }

    private function toRegularObject(BSONDocument $object): stdClass
    {
        $vObj = new stdClass();

        foreach ($object as $_k => $_v) {
            if ($_v instanceof BSONArray) {
                $vObj->{$_k} = $_v->bsonSerialize();
            } elseif ($_v instanceof BSONDocument) {
                $vObj->{$_k} = $this->toRegularObject($_v);
            } else {
                $vObj->{$_k} = $_v;
            }
        }

        return $vObj;
    }

    private function castArray(array $value, mixed $casting): mixed
    {
        ob_get_clean();

        $out = [];
        $valueCasting = (is_array($casting)) ? $casting[1] : $casting;

        foreach ($value as $key => $val) {
            // Encrypted string
            switch ($valueCasting) {
                case 'encrypted':
                    $out[$key] = $this->decryptValue($val);
                    break;

                case 'timestamp':
                    $out[$key] = $this->timestampToDate($val);
                    break;

                case ObjectId::class:
                    $out[$key] = $this->ensureObjectId($val);
                    break;

                default:
                    $caster = new $valueCasting();
                    $out[$key] = $caster->castTo($val);
                    break;
            }
        }

        // Only accept Collection as first casting option in array casting
        if (is_array($casting) && $casting[0] === Collection::class) {
            return new Collection($out);
        }

        return $out;
    }

    private function decryptValue(string $value): string
    {
        try {
            return Security::decrypt($value);
        } catch (FilesystemException|\SodiumException $e) {
            // Unable to decrypt, return original
            return $value;
        }
    }

    private function timestampToDate(int|float $value): Carbon
    {
        return Carbon::createFromTimestamp($value, setting('timezone', 'America/New_York'));
    }

    private function castDateTime(UTCDateTime $value, string $casting): mixed
    {
        if ($casting === Carbon::class) {
            return new Carbon($value->toDateTime());
        }

        if ($casting === \DateTime::class) {
            return $value->toDateTime();
        }

        return $value;
    }

    private function castObjectId(mixed $value)
    {
        if (is_string($value)) {
            return new ObjectId($value);
        }

        return $value;
    }
}