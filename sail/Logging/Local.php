<?php

namespace SailCMS\Logging;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use SailCMS\Filesystem;

class Local extends AbstractProcessingHandler
{
    private string $file;

    public function __construct(int|string|Level $level = Level::Debug, bool $bubble = true)
    {
        parent::__construct($level, $bubble);

        $this->file = Filesystem::getLogsPath() . '/web.' . date('d-m-Y') . '.log';
    }

    protected function write(LogRecord $record): void
    {
        file_put_contents($this->file, $record->formatted, FILE_APPEND);
    }
}