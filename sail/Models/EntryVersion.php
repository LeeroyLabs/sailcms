<?php

namespace SailCMS\Models;

use SailCMS\Collection;
use SailCMS\Database\Model;

/**
 *
 * @property int $created_at
 * @property string $user_id
 * @property Collection $entry // result of Entry =castFrom
 *
 */
class EntryVersion extends Model
{
    protected string $collection = 'entry_version';
    protected string $permissionGroup = 'entryversion'; // Usage only in get methods

    public function create(Collection $simplifyEntry)
    {

    }
}