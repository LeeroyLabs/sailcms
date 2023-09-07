<?php

namespace SailCMS\Models;

use Carbon\Carbon;
use SailCMS\Collection;
use SailCMS\Database\Model;
use SailCMS\Errors\DatabaseException;
use SailCMS\Types\Listing;
use SailCMS\Types\Pagination;

/**
 *
 * @property string $message
 * @property int    $date
 *
 */
class Log extends Model
{
    protected string $collection = 'logs';

    /**
     *
     * @param  string  $message
     * @param  array   $context
     * @return void
     * @throws DatabaseException
     *
     */
    public function write(string $message, array $context = []): void
    {
        $this->insert(['message' => str_replace("\n", " ", $message), 'context' => $context, 'date' => time()]);
    }

    /**
     *
     * Get list of loans
     *
     * @param  int       $page
     * @param  int       $limit
     * @param  int|null  $date_search
     * @return Listing
     * @throws DatabaseException
     *
     */
    public function getList(int $page = 1, int $limit = 25, int|null $date_search = null): Listing
    {
        $offset = $page * $limit - $limit;
        $query = [];

        if ($date_search && $date_search > 10000) {
            $date = Carbon::createFromTimestamp($date_search);
            $first_hour = strtotime("{$date->year}-{$date->month}-{$date->day} 00:00:00");
            $last_hour = strtotime("{$date->year}-{$date->month}-{$date->day} 23:59:59");
            $query['date'] = ['$gte' => $first_hour, '$lte' => $last_hour];
        }

        $total = $this->count($query);
        $pages = ceil($total / $limit);
        $pagination = new Pagination($page, $pages, $total);

        $list = $this->find($query)->sort(['date' => -1])->skip($offset)->limit($limit)->exec();

        return new Listing($pagination, new Collection($list));
    }

    /**
     *
     * Display php error logs
     *
     * @return string
     *
     */
    public function phpLogs(): string
    {
        $paths = [
            '/var/log/apache2/error.log',
            '/var/log/httpd/error.log',
            '/var/log/nginx/error.log',
            '/Applications/MAMP/logs/php_error.log'
        ];

        $output = '';

        foreach ($paths as $path) {
            if ($output === '' && file_exists($path)) {
                $output = shell_exec('cat ' . $path);
            }
        }

        if (!$output) {
            $output = 'file is empty';
        }

        $lines = array_reverse(explode("\n", $output));
        return implode("\n", $lines);
    }
}