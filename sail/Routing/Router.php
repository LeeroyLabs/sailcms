<?php

namespace SailCMS\Routing;

use SailCMS\Collection;
use SailCMS\Errors\RouteReturnException;
use SailCMS\Http\Response;
use SailCMS\Interfaces\AppController;

class Router
{
    private static Collection $routes;
    private static string $root = '/';

    public static function init(): void
    {
        static::$routes = new Collection([
            'get' => new Collection([]),
            'post' => new Collection([]),
            'put' => new Collection([]),
            'delete' => new Collection([])
        ]);
    }

    /**
     *
     * Set up a route using GET
     *
     * @param string $url
     * @param AppController|string $controller
     * @param string $method
     * @return void
     *
     */
    public function get(string $url, AppController|string $controller, string $method): void
    {
        if (is_string($controller)) {
            $controller = new $controller();
        }

        $route = new Route($url, $controller, $method);
        static::$routes->get('get')->push($route);
    }

    /**
     *
     * Set up a route using POST
     *
     * @param string $url
     * @param AppController|string $controller
     * @param string $method
     * @return void
     *
     */
    public function post(string $url, AppController|string $controller, string $method): void
    {
        if (is_string($controller)) {
            $controller = new $controller();
        }

        $route = new Route($url, $controller, $method);
        static::$routes->get('get')->push($route);
    }

    // TODO: PUT DELETE AND ANY

    /**
     *
     * Dispatch the route detection and execute and render matching route
     *
     * @throws RouteReturnException
     *
     */
    public static function dispatch(): void
    {
        $uri = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

        if ($uri === '') {
            $uri = '/';
        }

        if (!empty(static::$root) && static::$root !== '/') {
            static::$root = rtrim(static::$root, '/');

            if (self::$root === $uri) {
                $uri = '/';
            } else {
                // Remove the root directory from uri, remove only the first occurrence
                $uri = substr_replace($uri, '', strpos($uri, self::$root), strlen(self::$root));
            }
        }

        // TODO: PROCESS CMS routes first (look at urls in the database)

        // Not found, check if we have something defined
        $method = strtolower($_SERVER['REQUEST_METHOD']);

        foreach (static::$routes->get($method) as $num => $route) {
            $response = $route->matches($uri);

            if ($response) {
                if ($response instanceof Response) {
                    // TODO: RUN MIDDLEWARE
                    $response->render();
                } else {
                    throw new RouteReturnException('Route does not return a Response object. Cannot continue.', 0403);
                }
            }

            // TODO: 404
        }
    }
}