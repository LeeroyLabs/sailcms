<?php

namespace SailCMS\Logging;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use SailCMS\Errors\DatabaseException;
use SailCMS\Models\Log;

class Database extends AbstractProcessingHandler
{
    private Log $model;

    public function __construct(int|string|Level $level = Level::Debug, bool $bubble = true)
    {
        parent::__construct($level, $bubble);
        $this->model = new Log();
    }

    /**
     *
     * @throws DatabaseException
     *
     */
    protected function write(LogRecord $record): void
    {
        $this->model->write($record->formatted);
    }
}