<?php

namespace SailCMS\Models;

use SailCMS\Database\Model;
use SailCMS\Errors\DatabaseException;
use stdClass;

/**
 *
 * @property string   $type
 * @property string   $document_id
 * @property stdClass $data
 * @property int      $lastchange_date
 * @property string   $changed_by
 *
 */
class Seo extends Model
{
    protected string $collection = 'seo';
    protected string $permissionGroup = 'seo';

    /**
     *
     * Get a record by type
     *
     * @param  string  $type
     * @return Seo|null
     * @throws DatabaseException
     *
     */
    public static function getByType(string $type): ?Seo
    {
        $record = self::getBy('type', $type);

        if ($record) {
            $record->data = (object)$record->data->bsonSerialize();
        }

        return $record;
    }
}