<?php

namespace SailCMS\Models;

use SailCMS\Database\BaseModel;
use SailCMS\Errors\DatabaseException;

class Log extends BaseModel
{
    public function fields(bool $fetchAllFields = false): array
    {
        return ['message', 'date'];
    }

    /**
     *
     * @param  string  $message
     * @return void
     * @throws DatabaseException
     *
     */
    public function write(string $message): void
    {
        $this->insert(['message' => str_replace("\n", " ", $message), 'date' => time()]);
    }
}