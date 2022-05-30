<?php

namespace SailCMS\Routing;

use SailCMS\Http\Response;
use SailCMS\Blueprints\AppController;

class Route
{
    private string $url = '';
    private AppController|string $controller;
    private string $method = '';

    private static array $patterns = [
        ':any' => '([a-zA-Z0-9\-]+)',
        ':string' => '([a-zA-Z]+)',
        ':id' => '([0-9]{24})',
        ':num' => '([0-9]+)',
        ':all' => '(.*)'
    ];

    public function __construct(string $url, AppController|string $controller, string $method)
    {
        if (is_string($controller)) {
            $controller = new $controller();
        }

        $this->url = $url;
        $this->controller = $controller;
        $this->method = $method;
    }

    /**
     *
     * Try to match the route to the URL, if it does, execute the route
     *
     * @param string $url
     * @return Response|null
     *
     */
    public function matches(string $url): ?Response
    {
        $searches = array_keys(static::$patterns);
        $replaces = array_values(static::$patterns);

        // Check if route is defined without regex
        if ($url === $this->url) {
            $instance = new $this->controller();
            return call_user_func_array([$instance, $this->method], []);
        }

        if (str_contains($this->url, ':')) {
            $route = str_replace($searches, $replaces, $this->url);

            if (preg_match('#^' . $route . '$#', $url, $matched)) {

                unset($matched[0]);
                $matches = array_values($matched);

                $instance = new $this->controller();
                return call_user_func_array([$instance, $this->method], $matches);
            }
        }

        return null;
    }
}