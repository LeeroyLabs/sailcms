<?php

namespace SailCMS\Models;

use JsonException;
use MongoDB\BSON\Regex;
use SailCMS\Database\Model;
use SailCMS\Errors\DatabaseException;
use SailCMS\Types\SearchResults;

/**
 *
 * @property string $_id
 * @property string $document_id
 * @property string $title
 * @property string $content
 * @property string $type
 *
 */
class Search extends Model
{
    protected string $collection = 'search';

    /**
     *
     * Store or update a document
     *
     * @param array $document
     * @throws DatabaseException
     * @throws JsonException
     *
     */
    public function store(array $document): void
    {
        $doc = $this->findOne(['document_id' => (string)$document['document_id']])->exec();

        if ($doc) {
            $this->updateOne(['document_id' => $document['document_id']], ['$set' => $document]);
            return;
        }

        $this->insert($document);
    }

    /**
     *
     * Delete a document by the id
     *
     * @param string $id
     * @return void
     * @throws DatabaseException
     *
     */
    public function delete(string $id): void
    {
        $this->deleteOne(['document_id' => $id]);
    }

    /**
     *
     * Search for records
     *
     * @param string $search
     * @return SearchResults
     * @throws DatabaseException
     * @throws JsonException
     *
     */
    public function search(string $search): SearchResults
    {
        $records = $this->find([
            '$or' => [
                ['title' => new Regex($search, 'gi')],
                ['content' => new Regex($search, 'gi')]
            ]
        ])->exec();

        return new SearchResults($records, count($records));
    }
}