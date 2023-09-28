<?php

namespace SailCMS\Routing;

use SailCMS\Blueprints\AppController;
use SailCMS\Errors\DatabaseException;
use SailCMS\Models\Redirection;

class Redirect
{
    private string $from = '';
    private string $to = '';
    private bool $permanent;

    private static array $patterns = [
        ':any' => '([a-zA-Z0-9\-]+)',
        ':string' => '([a-zA-Z]+)',
        ':id' => '([0-9]+)',
        ':num' => '([0-9]+)',
        ':all' => '(.*)'
    ];

    public function __construct(string $from, string $to, $permanent)
    {
        $this->from = $from;
        $this->to = $to;
        $this->permanent = $permanent;
    }

    /**
     *
     * Match URL to a redirect route and execute it
     *
     * @param string $url
     * @return void
     *
     * @throws DatabaseException
     */
    public function matchAndExecute(string $url): void
    {
        $searches = array_keys(self::$patterns);
        $replaces = array_values(self::$patterns);

        // Static redirect found
        if ($url === $this->from) {
            $this->exec([]);
        }

        if (str_contains($this->from, ':')) {
            $route = str_replace($searches, $replaces, $this->from);

            if (preg_match('#^' . $route . '$#', $url, $matched)) {
                $redirection = (new Redirection())->getByUrl($url);
                if($redirection) {
                    (new Redirection())->updateHitCount($redirection->_id);
                }
                unset($matched[0]);
                $matches = array_values($matched);
                $this->exec($matches);
            }
        }
    }

    // -------------------------------------------------- Private --------------------------------------------------- //

    private function exec(array $matches): void
    {
        $to = $this->to;
        $spots = [];

        foreach ($matches as $num => $v) {
            $spots[] = '$' . ($num + 1);
        }

        $to = str_replace($spots, $matches, $to);

        if ($this->permanent) {
            header("HTTP/1.1 301 Moved Permanently");
        }else{
            header("HTTP/1.1 302 Found");
        }

        header("Location: " . $to);
        die();
    }
}