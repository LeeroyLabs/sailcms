<?php

namespace SailCMS\Database\Traits;

use Exception;
use MongoDB\Driver\Cursor;
use SailCMS\Database\Model;
use SailCMS\Errors\DatabaseException;

trait View
{
    const VIEW_SUFFIX = "_view";

    /**
     *
     * Create a vue from a Model collection
     *
     * @param  string  $viewName
     * @param  Model   $source
     * @param  array   $pipeline
     * @return void
     * @throws DatabaseException
     *
     */
    public function createView(string $viewName, Model $source, array $pipeline): ?Cursor
    {
        try {
            $result = $this->database->command([
                'create' => $this->setViewName($viewName),
                'viewOn' => $source->getCollection(),
                'pipeline' => $pipeline,
//                'collation' => $this->currentCollation
            ]);
        } catch (Exception $e) {
            throw new DatabaseException('0500: ' . $e->getMessage(), 0500);
        }
        // Change result
        return $result;
    }

    private function setViewName(string $name): string
    {
        return $name . self::VIEW_SUFFIX;
    }

    protected function updateView()
    {

    }

    protected function deleteView()
    {

    }
}