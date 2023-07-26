<?php

namespace SailCMS\Database\Traits;

use Exception;
use SailCMS\Database\Model;
use SailCMS\Debug;
use SailCMS\Errors\DatabaseException;
use SailCMS\Sail;

trait View
{
    // TODO permissions
    // TODO switch CRUD method to protected ?

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
        $list = $this->database->listCollectionNames();
        foreach ($list as $collection) {
            if ($this->setViewName($viewName) === $collection) {
                return true;
            }
        }
        return false;
    }

    /**
     *
     * Create a vue from a Model collection
     *
     * @param  string  $viewName
     * @param  Model   $source
     * @param  array   $pipeline
     * @return bool
     * @throws DatabaseException
     */
    public function createView(string $viewName, Model $source, array $pipeline): bool
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
            $result = $this->database->command([
                'create' => $this->setViewName($viewName),
                'viewOn' => $source->getCollection(),
                'pipeline' => $pipeline,
                'collation' => $collation
            ]);
            Debug::ray($result->toArray());
        } catch (Exception $e) {
            throw new DatabaseException('0500: ' . $e->getMessage(), 0500);
        }

        return true;
    }

    public function updateView()
    {

    }

    public function deleteView(string $viewName): bool
    {
        // TODO ***Protect*** (with setViewName ?) to delete only view

        try {
            $result = $this->database->command([
                'drop' => $this->setViewName($viewName)
            ]);
            Debug::ray($result->toArray());
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