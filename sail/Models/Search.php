<?php

namespace SailCMS\Models;

use MongoDB\BSON\Regex;
use SailCMS\Database\Model;
use SailCMS\Errors\DatabaseException;
use SailCMS\Types\SearchResults;

class Search extends Model
{
    public string $id = '';
    public string $title = '';
    public string $content = '';
    public string $type = '';

    public function __construct()
    {
        parent::__construct('search');
    }

    public function fields(bool $fetchAllFields = false): array
    {
        return ['_id', 'id', 'title', 'content', 'type'];
    }

    /**
     *
     * Store or update a document
     *
     * @param  array  $document
     * @throws DatabaseException
     *
     */
    public function store(array $document): void
    {
        $doc = $this->findOne(['id' => $document['_id']])->exec();

        if ($doc) {
            $this->updateOne(['id' => $document['_id']], ['$set' => $document]);
            return;
        }

        $this->insert($doc);
    }

    /**
     *
     * Delete a document by the id
     *
     * @param  string  $id
     * @return void
     * @throws DatabaseException
     *
     */
    public function remove(string $id): void
    {
        $this->deleteOne(['id' => $id]);
    }

    /**
     *
     * Search for records
     *
     * @param  string  $search
     * @return SearchResults
     * @throws DatabaseException
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