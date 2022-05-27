<?php

namespace SailCMS\Routing;

use JsonException;
use SailCMS\Blueprints\AppController;
use SailCMS\Collection;
use SailCMS\Errors\RouteReturnException;
use SailCMS\Http\Response;

class Router
{
    private static Collection $routes;
    private static Collection $redirects;
    private static string $root = '/';

    public static function init(): void
    {
        static::$routes = new Collection([
            'get' => new Collection([]),
            'post' => new Collection([]),
            'put' => new Collection([]),
            'delete' => new Collection([])
        ]);

        static::$redirects = new Collection([]);
    }

    /**
     *
     * Stop everything and redirect to given url
     *
     * @param string $url
     * @return void
     *
     */
    public function go(string $url): void
    {
        header('location ' . $url);
        exit();
    }

    /**
     *
     * Add a GET route
     *
     * @param string $url
     * @param AppController|string $controller
     * @param string $method
     * @return void
     *
     */
    public function get(string $url, AppController|string $controller, string $method): void
    {
        $this->addRoute('get', $url, $controller, $method);
    }

    /**
     *
     * Add a POST route
     *
     * @param string $url
     * @param AppController|string $controller
     * @param string $method
     * @return void
     *
     */
    public function post(string $url, AppController|string $controller, string $method): void
    {
        $this->addRoute('post', $url, $controller, $method);
    }

    /**
     *
     * Add a DELETE route
     *
     * @param string $url
     * @param AppController|string $controller
     * @param string $method
     * @return void
     *
     */
    public function delete(string $url, AppController|string $controller, string $method): void
    {
        $this->addRoute('delete', $url, $controller, $method);
    }

    /**
     *
     * Add a PUT route
     *
     * @param string $url
     * @param AppController|string $controller
     * @param string $method
     * @return void
     *
     */
    public function put(string $url, AppController|string $controller, string $method): void
    {
        $this->addRoute('put', $url, $controller, $method);
    }

    /**
     *
     * Add a redirected route
     *
     * @param string $from
     * @param string $to
     * @param AppController|string $controller
     * @param string $method
     * @return void
     *
     */
    public function redirect(string $from, string $to, AppController|string $controller = '', string $method = ''): void
    {
        if (is_string($controller) && $controller !== '') {
            $controller = new $controller();
        }

        $redirect = new Redirect($from, $to, $controller, $method);
        static::$redirects->push($redirect);
    }

    /**
     *
     * Dispatch the route detection and execute and render matching route
     *
     * @throws RouteReturnException|JsonException
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
        }

        // Try the redirect list to see if the url is one of them
        static::$redirects->each(function ($key, $redirect) use ($uri)
        {
            $redirect->matchAndExecute($uri);
        });

        // TODO 404 error!!!
    }

    // ----------------------------------------------- Private //

    private function addRoute(string $method, string $url, AppController|string $controller, string $callback): void
    {
        if (is_string($controller)) {
            $controller = new $controller();
        }

        $route = new Route($url, $controller, $callback);
        static::$routes->get($method)->push($route);
    }
}