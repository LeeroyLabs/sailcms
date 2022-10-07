<?php

namespace SailCMS\Routing;

use JsonException;
use League\Flysystem\FilesystemException;
use SailCMS\Collection;
use SailCMS\Contracts\AppController;
use SailCMS\Errors\FileException;
use SailCMS\Errors\RouteReturnException;
use SailCMS\Filesystem;
use SailCMS\Http\Response;
use SailCMS\Middleware;
use SailCMS\Middleware\Data;
use SailCMS\Middleware\Http;
use SailCMS\Types\MiddlewareType;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

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
            'delete' => new Collection([]),
            'any' => new Collection([])
        ]);

        static::$redirects = new Collection([]);
    }

    /**
     *
     * Stop everything and redirect to given url
     *
     * @param  string  $url
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
     * @param  string                $url
     * @param  string                $locale
     * @param  AppController|string  $controller
     * @param  string                $method
     * @param  string                $name
     * @return void
     *
     */
    public function get(string $url, string $locale, AppController|string $controller, string $method, string $name = ''): void
    {
        $this->addRoute('get', $url, $locale, $controller, $method);
    }

    /**
     *
     * Add a POST route
     *
     * @param  string                $url
     * @param  string                $locale
     * @param  AppController|string  $controller
     * @param  string                $method
     * @param  string                $name
     * @return void
     *
     */
    public function post(string $url, string $locale, AppController|string $controller, string $method, string $name = ''): void
    {
        $this->addRoute('post', $url, $locale, $controller, $method);
    }

    /**
     *
     * Add a DELETE route
     *
     * @param  string                $url
     * @param  string                $locale
     * @param  AppController|string  $controller
     * @param  string                $method
     * @param  string                $name
     * @return void
     *
     */
    public function delete(string $url, string $locale, AppController|string $controller, string $method, string $name = ''): void
    {
        $this->addRoute('delete', $url, $locale, $controller, $method, $name);
    }

    /**
     *
     * Add a PUT route
     *
     * @param  string                $url
     * @param  string                $locale
     * @param  AppController|string  $controller
     * @param  string                $method
     * @param  string                $name
     * @return void
     *
     */
    public function put(string $url, string $locale, AppController|string $controller, string $method, string $name = ''): void
    {
        $this->addRoute('put', $url, $locale, $controller, $method, $name);
    }

    /**
     *
     * Add a Any route
     *
     * @param  string                $url
     * @param  string                $locale
     * @param  AppController|string  $controller
     * @param  string                $method
     * @param  string                $name
     * @return void
     *
     */
    public function any(string $url, string $locale, AppController|string $controller, string $method, string $name = ''): void
    {
        $this->addRoute('any', $url, $locale, $controller, $method, $name);
    }

    /**
     *
     * Add a redirected route
     *
     * @param  string                $from
     * @param  string                $to
     * @param  AppController|string  $controller
     * @param  string                $method
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
     * @throws RouteReturnException|JsonException|FilesystemException|FileException|LoaderError|RuntimeError|SyntaxError
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

        // Method based routes
        foreach (static::$routes->get($method) as $num => $route) {
            $response = $route->matches($uri);

            if ($response) {
                if ($response instanceof Response) {
                    $response->render();
                    Middleware::execute(MiddlewareType::HTTP, new Data(Http::AfterRender, data: null));
                    die();
                }

                throw new RouteReturnException('Route does not return a Response object. Cannot continue.', 0400);
            }
        }

        // Any Routes
        foreach (static::$routes->get('any') as $num => $route) {
            $response = $route->matches($uri);

            if ($response) {
                if ($response instanceof Response) {
                    $response->render();
                    Middleware::execute(MiddlewareType::HTTP, new Data(Http::AfterRender, data: null));
                    die();
                }

                throw new RouteReturnException('Route does not return a Response object. Cannot continue.', 0400);
            }
        }

        // Try the redirect list to see if the url is one of them
        static::$redirects->each(function ($key, $redirect) use ($uri)
        {
            $redirect->matchAndExecute($uri);
        });

        $response = Response::html();
        $response->set('year', date('Y'));

        if ($uri === '/') {
            // If we are here, the cms did not pick up on the homepage, show built in homepage
            $response->template = 'welcome_default';
            $response->render();
            die();
        }

        $response->template = '404';
        $response->render();
        die();
    }

    /**
     *
     * Find the alternate routes for the given name
     *
     * @param  string  $name
     * @return Collection
     *
     */
    public function alternate(string $name): Collection
    {
        $alternateRoutes = new Collection([]);
        $methods = ['get', 'post', 'delete', 'put', 'any'];

        foreach ($methods as $method) {
            static::$routes->get($method)->each(static function ($key, $value) use (&$alternateRoutes, $method, $name)
            {
                if ($value->name === $method . '_' . $name && $value->locale !== Locale::$current) {
                    $alternateRoutes->push($value);
                }
            });
        }

        return $alternateRoutes;
    }

    // -------------------------------------------------- Private -------------------------------------------------- //

    private function addRoute(string $method, string $url, string $locale, AppController|string $controller, string $callback, string $name = ''): void
    {
        if (is_string($controller)) {
            $controller = new $controller();
        }

        // Since no name was given, use the url
        if (empty($name)) {
            $name = $url;
        }

        $route = new Route($name, $url, $locale, $controller, $callback);
        static::$routes->get($method)->push($route);
    }
}