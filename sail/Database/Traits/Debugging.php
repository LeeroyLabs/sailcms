<?php

namespace SailCMS\Database\Traits;

use SailCMS\Debug;

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
}