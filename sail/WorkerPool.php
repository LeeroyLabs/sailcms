<?php

namespace SailCMS;

use SailCMS\Errors\WorkerPoolException;
use SailCMS\MultiThreading\Worker;

class WorkerPool
{
    private int $maxWorkers = 5;
    private array $activeWorkers = [];

    public function __construct(int $maxWorkers = 5)
    {
        $this->maxWorkers = $maxWorkers;

        if (!function_exists('pcntl_wait')) {
            throw new WorkerPoolException('The PCNTL extension is not installed. This is required for the WorkerPool to function.', 500);
        }
    }

    /**
     *
     * Change worker limit after the pool is initialized
     *
     * @param  int  $maxWorkers
     * @return void
     *
     */
    public function setMaxWorkers(int $maxWorkers = 5)
    {
        $this->maxWorkers = $maxWorkers;
    }

    /**
     *
     * Get the maxWorkers value
     *
     * @return int
     *
     */
    public function getMaxWorkers(): int
    {
        return $this->maxWorkers;
    }

    /**
     *
     * Add a worker to the worker pool
     *
     * @param  Worker      $worker
     * @param  mixed|null  $data
     * @return void
     * @throws WorkerPoolException
     *
     */
    public function add(Worker $worker, mixed $data = null): void
    {
        $pid = pcntl_fork();

        // Failed to fork, stop now!
        if ($pid === -1) {
            throw new WorkerPoolException('Cannot fork process. Please make sure everything is installed properly.', 500);
        }

        if ($pid) {
            // We are in the parent, wait for things to be done and make sure we do not have zombie or hung processes.
            $this->activeWorkers[$pid] = true;

            if ($this->maxWorkers === count($this->activeWorkers)) {
                while (!empty($this->activeWorkers)) {
                    $closedPid = pcntl_wait($status);

                    if ($closedPid === -1) {
                        // Nothing left
                        $this->activeWorkers = [];
                    }

                    // Remove the worker when done
                    unset($this->activeWorkers[$closedPid]);
                }
            }
        } else {
            // Child process execution environment
            $worker->initialize($data);
            $thepid = getmypid();

            if ($worker->execute()) {
                $worker->onSuccess();
            } else {
                $worker->onFailure();
            }

            posix_kill($thepid, 9);
        }

        pcntl_wait($status, WNOHANG);
    }
}