<?php

namespace SailCMS\Search;

use MeiliSearch\Client;
use MeiliSearch\Endpoints\Indexes;
use SailCMS\Types\SearchResults;

class Meili implements Adapter
{
    private Client $client;
    private Indexes $index;

    public function __construct()
    {
        $url = $_ENV['MEILI_HOST'] . ':' . $_ENV['MEILI_PORT'];
        $mk = $_ENV['MEILI_MASTER_KEY'];
        $this->client = new Client($url, $mk);
        $this->index = $this->client->index($_ENV['MEILI_INDEX']);
    }

    public function store()
    {
        // TODO: Implement store() method.
    }

    /**
     *
     * Search Meili for given keywords
     *
     * @param string $search
     * @param array $meta
     * @param string $dataIndex
     * @return SearchResults
     *
     */
    public function search(string $search, array $meta = [], string $dataIndex = ''): SearchResults
    {
        if ($dataIndex === '') {
            $dataIndex = $_ENV['MEILI_INDEX'] ?? 'data';
        }

        // Set the index in which to search
        $this->index = $this->client->index($dataIndex);
        $results = $this->index->search($search, $meta);

        return new SearchResults($results->getHits(), $results->getHitsCount());
    }

    /**
     *
     * Update filterable attributes
     *
     * Note: Execute this using the "execute" method on the SailCMS\Search class.
     *
     * @param string $index
     * @param array $fields
     * @return bool
     *
     */
    public function updateFilterable(string $index, array $fields = []): bool
    {
        $this->client->index($index)->updateFilterableAttributes($fields);
        return true;
    }

    /**
     *
     * Return the instance of MeiliSearch for more custom requirements
     *
     * @return Client
     *
     */
    public function getRawAdapter(): Client
    {
        return $this->client;
    }

    /**
     *
     * Add given mock data for testing or development
     *
     * @param array $list
     * @return void
     *
     */
    public function addMockData(array $list): void
    {
        $this->index->addDocuments($list);
    }
}