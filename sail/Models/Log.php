<?php

namespace SailCMS\Models;

use SailCMS\Database\Model;
use SailCMS\Errors\DatabaseException;

/**
 *
 * @property string $message
 * @property int    $date
 *
 */
class Log extends Model
{
    protected string $collection = 'logs';

    /**
     *
     * @param  string  $message
     * @param  array   $context
     * @return void
     * @throws DatabaseException
     *
     */
    public function write(string $message, array $context = []): void
    {
        $this->insert(['message' => str_replace("\n", " ", $message), 'context' => $context, 'date' => time()]);
    }
}