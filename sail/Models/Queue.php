<?php

namespace SailCMS\Models;

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;
use MongoDB\Model\BSONDocument;
use SailCMS\Collection;
use SailCMS\Database\Model;
use SailCMS\Errors\DatabaseException;
use SailCMS\Queue\Task;
use SailCMS\Types\Listing;
use SailCMS\Types\Pagination;
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
 * @property int                   $pid
 * @property ?Collection           $logs
 *
 */
class Queue extends Model
{
    protected string $collection = 'queue';

    protected array $casting = [
        'logs' => Collection::class
    ];

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
            'pid' => 0,
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
     * Update the process ID of a task
     *
     * @param string $id
     * @param int $pid
     * @return bool
     *
     */
    public function updatePid(string $id, int $pid):bool
    {
        try {
            $info = [
                'pid' => $pid,
            ];

            $result = $this->updateOne(['_id' => $this->ensureObjectId($id)], [
                '$set' => $info
            ]);
        } catch (DatabaseException $exception) {
            return false;
        }

        return $result === 1;
    }

    /**
     *
     * Update the logs of a task
     *
     * @param string $id
     * @param Collection $logs
     * @return bool
     */
    public function updateLogs(string $id, Collection $logs):bool
    {
        try {
            $info = [
                'logs' => $logs,
            ];

            $result = $this->updateOne(['_id' => $this->ensureObjectId($id)], [
                '$set' => $info
            ]);
        } catch (DatabaseException $exception) {
            return false;
        }

        return $result === 1;
    }

    /**
     *
     * Change the schedule of a task
     *
     * @param string $id
     * @param int $timestamp
     * @return bool
     */
    public function changeSchedule(string $id, int $timestamp):bool
    {
        try {
            $info = [
                'scheduled_at' => $timestamp,
            ];

            $result = $this->updateOne(['_id' => $this->ensureObjectId($id)], [
                '$set' => $info
            ]);
        } catch (DatabaseException $exception) {
            return false;
        }

        return $result === 1;
    }

    /**
     *
     * Return task process ID
     *
     * @param string $id
     * @return int
     * @throws DatabaseException
     */
    public function getProcessId(string $id):int
    {
        return $this->findById($id)->exec()->pid;
    }

    /**
     *
     * Return task logs
     *
     * @param string $id
     * @return Collection
     * @throws DatabaseException
     */
    public function getLogs(string $id):Collection
    {
        return $this->findById($id)->exec()->logs;
    }

    /**
     *
     * Return current execution time of task
     *
     * @param int $pid
     * @return string
     */
    public function getTaskRunningTime(int $pid):string
    {
        return exec('ps -o etimes= -p ' . $pid, $out, $code);
    }

    /**
     *
     * Get task by id
     *
     * @param string $id
     * @return Queue|null
     * @throws DatabaseException
     *
     */
    public function getById(string $id): ?Queue
    {
        return $this->findById($id)->exec();
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
     * Get a list of tasks to perform
     *
     * @param int $page
     * @param int $limit
     * @param string $search
     * @param string $sort
     * @param int $direction
     * @return Listing
     * @throws DatabaseException
     */
    public function searchTasks(
        int $page = 0,
        int $limit = 25,
        string $search = '',
        string $sort = 'name',
        int $direction = Model::SORT_ASC
    ): Listing
    {
        $offset = $page * $limit - $limit;

        $options = QueryOptions::initWithSort([$sort => $direction]);
        $options->skip = $offset;
        $options->limit = $limit;

        $query = [];

        if ($search !== '') {
            $query['name'] = new Regex($search, 'gi');
        }

        // Pagination
        $total = $this->count($query);
        $pages = ceil($total / $limit);
        $current = $page;
        $pagination = new Pagination($current, (int)$pages, $total);

        $list = $this->find($query, $options)->exec();

        return new Listing($pagination, new Collection($list));
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
     * @param ObjectId|string $id
     * @return bool
     * @throws DatabaseException
     */
    public function cancelTask(ObjectId|string $id): bool
    {
        $this->deleteById($this->ensureObjectId($id));
        return true;
    }

    /**
     *
     * Stop the process of a task
     *
     * @param int $pid
     * @return bool
     */
    public function stopTask(int $pid): bool
    {
        exec("kill -9 " . $pid,  $out, $code);
        return true;
    }
}