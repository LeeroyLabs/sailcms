<?php

namespace SailCMS\Database\Traits;

use SailCMS\Debug;
use SailCMS\Log;

trait Debugging
{
    /**
     *
     * Debug the db call just called
     *
     * @param  string  $op
     * @param  float   $time
     * @param  array   $extra
     * @return void
     *
     */
    private function debugCall(string $op = '', float $time = 0, array $extra = []): void
    {
        $debugQuery = [
            'operation' => ($op === '') ? $this->currentOp : $op,
            'query' => $extra['query'] ?? $this->currentQuery,
            'projection' => $this->currentProjection ?? [],
            'sort' => $this->currentSort ?? [],
            'offset' => $this->currentSkip ?? 0,
            'limit' => $this->currentLimit ?? 10_000,
            'model' => get_class($this),
            'collection' => $this->active_collection->getCollectionName(),
            'time' => $time,
            'update' => $extra['update'] ?? [],
            'pipeline' => $extra['pipeline'] ?? []
        ];

        Debug::endQuery($debugQuery);
    }

    /**
     *
     * Send logs to the logger if enabled
     *
     * @param  string  $method
     * @param  array   $query
     * @param  bool    $status
     * @return void
     *
     */
    private function log(string $method, array $query, bool $status = false): void
    {
        $log = setting('logging.database', false);
        $env = env('environment', 'dev');

        // The log model does not log (infinite loop)
        if ((static::class !== \SailCMS\Models\Log::class) && $log && ($env === 'dev' || $env === 'development')) {
            if ($status) {
                Log::debug($method, $query);
                return;
            }

            Log::error($method, $query);
        }
    }
}