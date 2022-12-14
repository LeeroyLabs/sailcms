<?php

namespace SailCMS\Routing;

use SailCMS\Blueprints\AppController;

class Redirect
{
    private string $from = '';
    private string $to = '';

    private static array $patterns = [
        ':any' => '([a-zA-Z0-9\-]+)',
        ':string' => '([a-zA-Z]+)',
        ':id' => '([0-9]+)',
        ':num' => '([0-9]+)',
        ':all' => '(.*)'
    ];

    public function __construct(string $from, string $to)
    {
        $this->from = $from;
        $this->to = $to;
    }

    /**
     *
     * Match URL to a redirect route and execute it
     *
     * @param  string  $url
     * @return void
     *
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

        header("HTTP/1.1 301 Moved Permanently");
        header("Location: " . $to);
        die();
    }
}