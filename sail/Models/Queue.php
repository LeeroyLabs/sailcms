<?php

namespace SailCMS\Models;

use MongoDB\BSON\ObjectId;
use SailCMS\Collection;
use SailCMS\Database\Model;
use SailCMS\Errors\DatabaseException;
use SailCMS\Queue\Task;
use SailCMS\Types\QueryOptions;

class Queue extends Model
{
    public string $name;
    public int $scheduled_at;
    public int $executed_at;
    public bool $executed;
    public string $execution_result;
    public bool $execution_success;
    public bool $locked;
    public string $handler;
    public string $action;
    public bool $retriable;
    public int $retry_count;
    public Collection $settings;
    public int $priority;

    public function fields(bool $fetchAllFields = false): array
    {
        return [
            '_id',
            'scheduled_at',
            'locked',
            'executed',
            'executed_at',
            'name',
            'handler',
            'action',
            'settings',
            'retriable',
            'retry_count',
            'priority',
            'execution_result',
            'execution_success'
        ];
    }

    /**
     *
     * Add a task to the queue
     *
     * @param  Task  $task
     * @return void
     * @throws DatabaseException
     *
     */
    public static function add(Task $task): void
    {
        $instance = new static();

        $instance->insert([
            'name' => $task->name,
            'handler' => $task->handler,
            'action' => $task->action,
            'settings' => $task->settings,
            'priority' => $task->priority,
            'retriable' => $task->retriable,
            'retry_count' => 0,
            'scheduled_at' => time(),
            'locked' => false,
            'executed' => false,
            'executed_at' => 0,
            'execution_result' => '',
            'execution_success' => false
        ]);
    }

    /**
     *
     * Get a list of tasks to perform
     *
     * @param  int  $limit
     * @return Collection
     * @throws DatabaseException
     *
     */
    public function get(int $limit = 0): Collection
    {
        $options = new QueryOptions();
        $options->limit = $limit;
        $options->sort = ['priority' => 1];

        return new Collection($this->find(['locked' => false, 'executed' => false], $options)->exec());
    }

    /**
     *
     * Check if a task is locked or not
     *
     * @param  ObjectId  $id
     * @return bool
     * @throws DatabaseException
     *
     */
    public function checkLockStatus(ObjectId $id): bool
    {
        $task = $this->findById($id)->exec();
        return !($task && $task->executed === false && !$task->locked);
    }

    /**
     *
     * Lock or unlock a task
     *
     * @param  ObjectId  $id
     * @param  bool      $status
     * @return void
     * @throws DatabaseException
     *
     */
    public function setLockStatus(ObjectId $id, bool $status): void
    {
        $this->updateOne(['_id' => $id], ['$set' => ['locked' => $status]]);
    }

    /**
     *
     * Close a task after success or failure
     *
     * @param  ObjectId  $id
     * @param  string    $message
     * @param  bool      $successful
     * @param  int       $retryCount
     * @return void
     * @throws DatabaseException
     *
     */
    public function closeTask(ObjectId $id, string $message, bool $successful = true, int $retryCount = -1): void
    {
        $update = [
            '$set' => [
                'locked' => false,
                'executed_at' => time(),
                'execution_result' => $message,
                'execution_success' => $successful
            ]
        ];

        if ($retryCount > -1) {
            $update['$set']['retry_count'] = $retryCount;

            if ($retryCount < env('queue_max_retry', 3)) {
                $update['$set']['executed'] = false;
            } else {
                $update['$set']['executed'] = true;
            }
        } else {
            $update['$set']['executed'] = true;
        }

        $this->updateOne(['_id' => $id], $update);
    }
}