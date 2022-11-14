<?php

namespace [LOCATION]\Models;

use SailCMS\Database\Model;
use SailCMS\Errors\DatabaseException;

class [NAME] extends BaseModel
{
    public function fields(bool $fetchAllFields = false): array
    {
        return [];
    }

    protected function processOnFetch(string $field, mixed $value): mixed
    {
        // This is where you can cast a certain value to specific type of class instead of standard stdClass
        // If not needed at all, you can remove this method, it's implemented in the parent class.
        return $value;
    }

    protected function processOnStore(string $field, mixed $value): mixed
    {
        // This is where you can change the format or cast a value to a different thing before it gets saved in the
        // database. If not needed at all, you can remove this method. It's implemented in the parent class.
        return $value;
    }
}