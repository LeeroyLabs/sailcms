<?php

namespace SailCMS\GraphQL\Controllers;

use SailCMS\CLI;
use SailCMS\Collection;
use SailCMS\Errors\DatabaseException;
use SailCMS\GraphQL\Context;
use SailCMS\Models\Queue as QueueModel;
use SailCMS\Queue as QueueMan;
use SailCMS\Queue\Task;
use SailCMS\Types\Listing;

class Queue
{
    /**
     *
     * Get a tasks
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return QueueModel
     * @throws DatabaseException
     *
     */
    public function getTask(mixed $obj, Collection $args, Context $context): QueueModel
    {
        return (new QueueModel())->getById($args->get('id'));
    }

    /**
     *
     * Get all tasks
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return Collection|null
     * @throws DatabaseException
     *
     */
    public function getList(mixed $obj, Collection $args, Context $context): Collection|null
    {
        return (new QueueModel())->getList($args->get('limit', 0));
    }

    /**
     *
     * Return current process time of a task
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return string
     */
    public function getTaskRunningTime(mixed $obj, Collection $args, Context $context): string
    {
        return (new QueueModel())->getTaskRunningTime($args->get('pid'));
    }

    /**
     *
     * Get all tasks
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return Listing
     * @throws DatabaseException
     *
     */
    public function searchTasks(mixed $obj, Collection $args, Context $context): Listing
    {
        return (new QueueModel())->searchTasks(
            $args->get('page'),
            $args->get('limit'),
            $args->get('search', ''),
            $args->get('sort', 'name'),
            ($args->get('order', 1) === 'DESC') ? -1 : 1
        );
    }

    /**
     *
     * Create a task
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return bool
     * @throws DatabaseException
     * @throws \JsonException
     *
     */
    public function createTask(mixed $obj, Collection $args, Context $context): bool
    {
        $action = $args->get('action');

        if (!str_contains($action, 'php sail')) {
            $action = 'php sail ' . $action;
        }

        $task = new Task(
            $args->get('name'),
            $args->get('retriable'),
            '',
            $action,
            json_decode($args->get('settings', '{}'), false, 512, JSON_THROW_ON_ERROR),
            $args->get('priority'),
            $args->get('timestamp', time())
        );

        QueueModel::add($task);
        return true;
    }

    /**
     *
     * Update a task
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return bool
     * @throws \JsonException
     *
     */
    public function updateTask(mixed $obj, Collection $args, Context $context): bool
    {
        $action = $args->get('action');

        if (!str_contains($action, 'php sail')) {
            $action = 'php sail ' . $action;
        }

        $task = new Task(
            $args->get('name'),
            $args->get('retriable'),
            '',
            $action,
            json_decode($args->get('settings', '{}'), false, 512, JSON_THROW_ON_ERROR),
            $args->get('priority'),
            $args->get('timestamp', time())
        );

        QueueModel::update($args->get('id'), $task);
        return true;
    }

    /**
     *
     * Start all tasks
     *
     * @throws DatabaseException
     */
    public function startAllTasks(mixed $obj, Collection $args, Context $context): bool
    {
        $queue = QueueMan::manager();
        $queue->process();
        return true;
    }

    /**
     *
     * Start tasks
     *
     * @throws DatabaseException
     */
    public function startTasks(mixed $obj, Collection $args, Context $context): bool
    {
        $tasks = [];

        foreach ($args->get('ids') as $id) {
            $tasks[] = (new QueueModel())->getById($id);
        }

        $queue = QueueMan::manager();
        $queue->process(new Collection($tasks));
        return true;
    }

    /**
     *
     * Get all CLI command
     *
     */
    public function cliCommand(mixed $obj, Collection $args, Context $context): Collection
    {
        return CLI::$registeredCommands;
    }

    /**
     *
     * Get logs of a task
     *
     * @throws DatabaseException
     */
    public function getTaskLogs(mixed $obj, Collection $args, Context $context): Collection
    {
        return (new QueueModel())->getLogs($args->get('id'));
    }

    /**
     *
     * Change the schedule of a task
     *
     */
    public function changeTaskSchedule(mixed $obj, Collection $args, Context $context): bool
    {
        return (new QueueModel())->changeSchedule($args->get('id'), $args->get('timestamp'));
    }

    /**
     * @throws DatabaseException
     */
    public function retryTask(mixed $obj, Collection $args, Context $context): bool
    {
        $task = new Collection([(new QueueModel())->getById($args->get('id'))]);

        $queue = QueueMan::manager();
        $queue->process($task);
        return true;
    }

    /**
     *
     * Stop a tasks
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return bool
     */
    public function stopTask(mixed $obj, Collection $args, Context $context): bool
    {
        return (new QueueModel())->stopTask($args->get('pid'));
    }

    /**
     *
     * Stop a tasks
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return bool
     * @throws DatabaseException
     */
    public function stopAllTasks(mixed $obj, Collection $args, Context $context): bool
    {
        $tasks = (new QueueModel())->getList();

        foreach ($tasks as $task) {
            if ($task->pid) {
                (new QueueModel())->stopTask($task->pid);
            }
        }

        return true;
    }

    /**
     *
     * Cancel a tasks
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return bool
     * @throws DatabaseException
     */
    public function cancelTask(mixed $obj, Collection $args, Context $context): bool
    {
        return (new QueueModel())->cancelTask($args->get('id'));
    }
}