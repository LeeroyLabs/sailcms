<?php

namespace SailCMS;

use Exception;
use SailCMS\Errors\DatabaseException;
use \SailCMS\Models\Queue as QueueModel;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

final class Queue
{
    private static Queue $manager;

    private function __construct()
    {
    }

    /**
     *
     * Get an instance of the Queue
     *
     * @return Queue
     *
     */
    public static function manager(): Queue
    {
        if (!isset(self::$manager)) {
            self::$manager = new self();
        }

        return self::$manager;
    }

    /**
     *
     * Process queue items
     *
     * Note: All = 50 000 items max
     *
     * @param Collection|null $tasks
     * @return void
     * @throws DatabaseException
     */
    public function process(Collection $tasks = null): void
    {
        $maxProcess = env('queue_max_process_per_run', 'all');

        if ($maxProcess !== 'all') {
            $maxProcess = (int)$maxProcess;
        } else {
            $maxProcess = 50_000;
        }

        $model = new QueueModel();
        if (!$tasks) {
            $tasks = $model->getList($maxProcess);
        }

        $tasks->each(function ($key, $value) use ($model)
        {
            $locked = $model->checkLockStatus($value->_id);
            $retry_count = 0;

            if ($value->retry_count === env('queue_max_retry', 3)) {
                $model->closeTask($value->_id, 'Too many retries', false);
            }

            if ($value->retriable) {
                $retry_count = $value->retry_count + 1;
            }

            if (!$locked) {
                $model->setLockStatus($value->_id, true);



//                    if (method_exists($instance, $value->action)) {
                        try {
//                            $result = $instance->{$value->action}(...$value->settings->unwrap());
//
//                            if (empty($result)) {
//                                $result = 'Executed successfully with no return';
//                            } elseif (!is_string($result) && !is_scalar($result)) {
//                                $result = json_encode($result, JSON_THROW_ON_ERROR);
//                            }

                            $action = explode(' ', $value->action);

                            $result = new Process($action);
                            $result->start();

                            $pid = $result->getPid();
                            (new QueueModel)->updatePid($value->_id, $pid);

                            $result->wait();

//                            if (!$result->isSuccessful()) {
//                                throw new ProcessFailedException($result);
//                            }

                            $model->closeTask($value->_id, $result->getOutput());
                        } catch (Exception $e) {
                            $model->closeTask($value->_id, "Execution failed: {$e->getMessage()}.", false, $retry_count);
                        }
//                    } else {
//                        $model->closeTask($value->_id, "Action '{$value->action}' does not exist, please make sure it exists.", false, $retry_count);
//                    }
            }
        });
    }
}