<?php

namespace SailCMS\Models;

use MongoDB\BSON\ObjectId;
use MongoDB\Model\BSONDocument;
use SailCMS\Collection;
use SailCMS\Database\Model;
use SailCMS\Errors\DatabaseException;
use SailCMS\Queue\Task;
use SailCMS\Types\QueryOptions;
use stdClass;

/**
 *
 * @property string                $name
 * @property int                   $scheduled_at
 * @property int                   $executed_at
 * @property bool                  $executed
 * @property string                $execution_result
 * @property bool                  $execution_success
 * @property bool                  $locked
 * @property string                $handler
 * @property string                $action
 * @property bool                  $retriable
 * @property int                   $retry_count
 * @property BSONDocument|stdClass $settings
 * @property int                   $priority
 *
 */
class Queue extends Model
{
    protected string $collection = 'queue';

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
        self::query()->insert([
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
    public function getList(int $limit = 0): Collection
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

    /**
     *
     * Get the amount of task of given name the system has
     *
     * @param  string  $task
     * @return int
     *
     */
    public function getCountForTask(string $task): int
    {
        return self::query()->count(['action' => $task]);
    }

    /**
     *
     * Does the given task exist already
     *
     * @param  string  $task
     * @return bool
     *
     */
    public static function taskExists(string $task): bool
    {
        $count = (new self)->getCountForTask($task);
        return ($count > 0);
    }

    /**
     *
     * Delete a task
     *
     * @param ObjectId $id
     * @return bool
     * @throws DatabaseException
     *
     */
    public function cancelTask(ObjectId $id): bool
    {
        $this->deleteById($id);
        return true;
    }

    /**
     *
     * Stop the process of a task
     *
     * @param ObjectId $id
     * @param string $pid
     * @return bool
     *
     */
    public function stopTask(ObjectId $id, string $pid): bool
    {
        shell_exec('kill -9 ' . $pid);
        return true;
    }
}