<?php

namespace SailCMS\Database\Traits;

use Exception;
use MongoDB\Collection;
use SailCMS\Errors\DatabaseException;
use SailCMS\Log;
use SailCMS\Sail;

trait View
{
    protected Collection $active_view;
    protected bool $use_view = false;

    /**
     *
     * Use a view to make a query
     *
     * @param  string  $viewName
     * @return $this
     *
     */
    public function useView(string $viewName): static
    {
        if ($this->viewExists($viewName)) {
            $view = $this->client->selectCollection(env('database_db', 'sailcms'), $this->setViewName($viewName));

            $this->active_view = $view;
            $this->use_view = true;
        } else {
            // LOG errors when fail silently
            Log::warning("View '$viewName' does not exists.", ['fullViewName' => $this->setViewName($viewName)]);
        }

        return $this;
    }


    /**
     *
     * Check if a view exists
     *
     * @param  string  $viewName
     * @return bool
     *
     */
    public function viewExists(string $viewName): bool
    {
        $database = $this->client->selectDatabase(env('database_db', 'sailcms'));
        $list = $database->listCollectionNames();
        foreach ($list as $collection) {
            if ($this->setViewName($viewName) === $collection) {
                return true;
            }
        }
        return false;
    }

    /**
     *
     * Create a view for the current Model if not exists
     *
     * @param  string  $viewName
     * @param  array   $pipeline
     * @return bool
     * @throws DatabaseException
     */
    public function createView(string $viewName, array $pipeline): bool
    {
        $collation = null;
        if ($this->currentCollation !== '') {
            $collation = [
                'locale' => $this->currentCollation,
                'strength' => 3
            ];
        }

        if ($this->viewExists($viewName)) {
            return false;
        }

        try {
            $database = $this->client->selectDatabase(env('database_db', 'sailcms'));
            $database->command([
                'create' => $this->setViewName($viewName),
                'viewOn' => $this->getCollection(),
                'pipeline' => $pipeline,
                'collation' => $collation
            ]);
        } catch (Exception $e) {
            throw new DatabaseException('0500: ' . $e->getMessage(), 0500);
        }

        return true;
    }

    /**
     *
     * Delete the test view if exists
     *  uses only in unit tests
     *
     * @return bool
     * @throws DatabaseException
     */
    public function deleteTestView(): bool
    {
        if (!$this->viewExists('test')) {
            return false;
        }

        try {
            $database = $this->client->selectDatabase(env('database_db', 'sailcms'));
            $database->command([
                'drop' => $this->setViewName('test')
            ]);
        } catch (Exception $e) {
            throw new DatabaseException('0500: ' . $e->getMessage(), 0500);
        }

        return true;
    }

    /**
     *
     * Bottleneck to set view name
     *
     * @param  string  $name
     * @return string
     *
     */
    private function setViewName(string $name): string
    {
        return $name . "_" . Sail::siteId();
    }

}