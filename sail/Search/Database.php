<?php

namespace SailCMS\Search;

use SailCMS\Collection;
use SailCMS\Contracts\SearchAdapter;
use SailCMS\Errors\DatabaseException;
use SailCMS\Models\Search;
use SailCMS\Types\SearchResults;

class Database implements SearchAdapter
{
    /**
     *
     * Store or update a document
     *
     * @param  object|array  $document
     * @param  string        $dataIndex
     * @throws DatabaseException
     *
     */
    public function store(object|array $document, string $dataIndex = '')
    {
        if ($document instanceof Collection) {
            $document = $document->unwrap();
        } else {
            $document = (array)$document;
        }

        if (isset($document['_id'])) {
            $document['id'] = (string)$document['_id'];
            unset($document['_id']);
        }

        $search = new Search();
        $search->store($document);
    }

    /**
     *
     * Delete a document by id
     *
     * @param  string  $id
     * @param  string  $dataIndex
     * @return void
     * @throws DatabaseException
     *
     */
    public function remove(string $id, string $dataIndex = '')
    {
        $search = new Search();
        $search->delete($id);
    }

    /**
     *
     * Search for the keywords (in title and content)
     *
     * @param  string  $search
     * @param  array   $meta
     * @param  string  $dataIndex
     * @return SearchResults
     * @throws DatabaseException
     *
     */
    public function search(string $search, array $meta = [], string $dataIndex = ''): SearchResults
    {
        $model = new Search();
        return $model->search($search);
    }

    /**
     *
     * Send an instance of the search model
     *
     * @return mixed
     *
     */
    public function getRawAdapter(): mixed
    {
        return new Search();
    }
}