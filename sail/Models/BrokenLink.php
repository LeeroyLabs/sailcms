<?php

namespace SailCMS\Models;

use MongoDB\BSON\Regex;
use Respect\Validation\Exceptions\DateException;
use SailCMS\Collection;
use SailCMS\Database\Model;
use SailCMS\Errors\DatabaseException;
use SailCMS\Types\Listing;
use SailCMS\Types\Pagination;
use SailCMS\Types\QueryOptions;

/**
 *
 * @property string $url
 * @property int $hit_count
 * @property int $last_attempt
 */
class BrokenLink extends Model
{
    protected string $collection = 'broken_link';

    /**
     *
     * Add a broken link
     *
     * @param string $url
     * @return bool
     * @throws DatabaseException
     */
    public static function add(string $url): bool
    {
        try {
            $result = self::query()->insert([
                'url' => $url,
                'hit_count' => 1,
                'last_attempt' => time()
            ]);
        } catch (DateException $exception) {
            return false;
        }

        return $result === 1;
    }

    /**
     *
     * Update broken link hit count
     *
     * @param string $url
     * @return bool
     * @throws DatabaseException
     */
    public function update(string $url): bool
    {
        $broken_link = $this->getByUrl($url);

        $info = [
            'hit_count' => $this->getHitCount($broken_link->id) + 1,
            'last_attempt' => time()
        ];

        try {
            $result = $this->updateOne(['_id' => $this->ensureObjectId($broken_link->_id)], [
                '$set' => $info
            ]);
        } catch (DateException $exception) {
            return false;
        }

        return $result === 1;
    }

    /**
     *
     * Get broken link by url
     *
     * @param string $url
     * @return Redirection|null
     * @throws DatabaseException
     *
     */
    public function getByUrl(string $url): ?BrokenLink
    {
        return $this->findOne(['url' => $url])->exec();
    }

    /**
     *
     * Get broken link hit count
     *
     * @param string $id
     * @return int
     * @throws DatabaseException
     */
    public function getHitCount(string $id):int
    {
        return $this->findById($id)->exec()->hit_count;
    }

    /**
     *
     * Get a list of broken links
     *
     * @param int $page
     * @param int $limit
     * @param string $search
     * @param string $sort
     * @param int $direction
     * @return Listing
     * @throws DatabaseException
     */
    public function getList(
        int $page = 0,
        int $limit = 25,
        string $search = '',
        string $sort = 'name',
        int $direction = Model::SORT_ASC
    ): Listing
    {
        $offset = $page * $limit - $limit;

        $options = QueryOptions::initWithSort([$sort => $direction]);
        $options->skip = $offset;
        $options->limit = $limit;

        $query = [];

        if ($search !== '') {
            $query['name'] = new Regex($search, 'gi');
        }

        // Pagination
        $total = $this->count($query);
        $pages = ceil($total / $limit);
        $current = $page;
        $pagination = new Pagination($current, (int)$pages, $total);

        $list = $this->find($query, $options)->exec();

        return new Listing($pagination, new Collection($list));
    }

    /**
     *
     * Delete a broken link
     *
     * @param string $id
     * @return bool
     * @throws DatabaseException
     */
    public function delete(string $id): bool
    {
        $this->deleteById($this->ensureObjectId($id));
        return true;
    }
}