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
 * @property string $redirect_url
 * @property string $redirect_type
 * @property int $hit_count
 * @property int $last_attempt
 */
class Redirection extends Model
{
    protected string $collection = 'redirection';

    /**
     *
     * Add a redirection
     *
     * @param string $url
     * @param string $redirect_url
     * @param string $redirect_type
     * @return bool
     * @throws DatabaseException
     */
    public static function add(string $url, string $redirect_url, string $redirect_type): bool
    {
        if (!(new self)->getByUrl($url)) {
            try {
                $result = self::query()->insert([
                    'url' => $url,
                    'redirect_url' => $redirect_url,
                    'redirect_type' => $redirect_type,
                    'hit_count' => 0,
                    'last_attempt' => 0
                ]);
            }catch (DateException $exception) {
                return false;
            }

            return true;
        }

        return false;
    }

    /**
     *
     * Update a redirection
     *
     * @param string $id
     * @param string $url
     * @param string $redirect_url
     * @param string $redirect_type
     * @return bool
     * @throws DatabaseException
     */
    public function update(string $id, string $url, string $redirect_url, string $redirect_type): bool
    {
        $info = [
            'url' => $url,
            'redirect_url' => $redirect_url,
            'redirect_type' => $redirect_type
        ];

        try {
            $result = $this->updateOne(['_id' => $this->ensureObjectId($id)], [
                '$set' => $info
            ]);
        }catch (DateException $exception) {
            return false;
        }

        return $result === 1;
    }

    /**
     *
     * Update redirection hit count
     *
     * @param string $id
     * @return bool
     * @throws DatabaseException
     */
    public function updateHitCount(string $id): bool
    {
        $info = [
            'hit_count' => $this->getHitCount($id) + 1,
            'last_attempt' => time()
        ];

        try {
            $result = $this->updateOne(['_id' => $this->ensureObjectId($id)], [
                '$set' => $info
            ]);
        }catch (DateException $exception) {
            return false;
        }

        return $result === 1;
    }

    /**
     *
     * Get task by id
     *
     * @param string $id
     * @return Redirection|null
     * @throws DatabaseException
     *
     */
    public function getById(string $id): ?Redirection
    {
        return $this->findById($id)->exec();
    }

    /**
     *
     * Get redirection by url
     *
     * @param string $url
     * @return Redirection|null
     * @throws DatabaseException
     *
     */
    public function getByUrl(string $url): ?Redirection
    {
        return $this->findOne(['url' => $url])->exec();
    }

    /**
     *
     * Get redirection hit count
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
     * Get a list of redirections
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
     * Delete a redirection
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