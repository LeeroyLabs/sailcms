<?php

namespace SailCMS\GraphQL\Controllers;

use SailCMS\Collection;
use SailCMS\Errors\DatabaseException;
use SailCMS\GraphQL\Context;
use SailCMS\Models\Queue as QueueModel;
use SailCMS\Queue\Task;

class Queue
{
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
        return (new QueueModel())->stopTask($args->get('id'), $args->get('pid'));
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