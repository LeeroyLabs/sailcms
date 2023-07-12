<?php

namespace SailCMS\GraphQL\Controllers;

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
     * @param mixed $obj
     * @param Collection $args
     * @param Context $context
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
     * @param mixed $obj
     * @param Collection $args
     * @param Context $context
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
     * Get all tasks
     *
     * @param mixed $obj
     * @param Collection $args
     * @param Context $context
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
     * Create a tasks
     *
     * @param mixed $obj
     * @param Collection $args
     * @param Context $context
     * @return bool
     * @throws DatabaseException
     */
    public function createTask(mixed $obj, Collection $args, Context $context): bool
    {
        $task = new Task(
            $args->get('name'),
            $args->get('retriable'),
            $args->get('handler'),
            $args->get('action'),
            new Collection([$args->get('settings')]),
            $args->get('priority'),
        );
        QueueModel::add($task);
        return true;
    }

    /**
     * @throws DatabaseException
     */
    public function startAllTasks(mixed $obj, Collection $args, Context $context): bool
    {
        $queue = QueueMan::manager();
        $queue->process();
        return true;
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
     * @param mixed $obj
     * @param Collection $args
     * @param Context $context
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
     * @param mixed $obj
     * @param Collection $args
     * @param Context $context
     * @return bool
     * @throws DatabaseException
     */
    public function stopAllTasks(mixed $obj, Collection $args, Context $context): bool
    {
        $tasks = (new QueueModel())->getList();

        foreach ($tasks as $task) {
            if($task->pid) {
                (new QueueModel())->stopTask($task->pid);
            }
        }

        return true;
    }

    /**
     *
     * Cancel a tasks
     *
     * @param mixed $obj
     * @param Collection $args
     * @param Context $context
     * @return bool
     * @throws DatabaseException
     */
    public function cancelTask(mixed $obj, Collection $args, Context $context): bool
    {
        return (new QueueModel())->cancelTask($args->get('id'));
    }
}