<?php

namespace SailCMS\Routing;

use JsonException;
use League\Flysystem\FilesystemException;
use SailCMS\Collection;
use SailCMS\Contracts\AppContainer;
use SailCMS\Contracts\AppController;
use SailCMS\Debug;
use SailCMS\Debug\DebugController;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\EntryException;
use SailCMS\Errors\FileException;
use SailCMS\Errors\PermissionException;
use SailCMS\Errors\RouteReturnException;
use SailCMS\Http\Response;
use SailCMS\Locale;
use SailCMS\Middleware;
use SailCMS\Middleware\Data;
use SailCMS\Middleware\Http;
use SailCMS\Models\Entry;
use SailCMS\Register;
use SailCMS\Sail;
use SailCMS\Types\MiddlewareType;
use SodiumException;
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
        self::$routes = new Collection([
            'get' => Collection::init(),
            'post' => Collection::init(),
            'put' => Collection::init(),
            'delete' => Collection::init(),
            'any' => Collection::init()
        ]);

        self::$redirects = Collection::init();
    }

    /**
     *
     * Get all routes of the given method
     *
     * @param  string  $method
     * @return Collection
     *
     */
    public static function getAll(string $method): Collection
    {
        return self::$routes->get(strtolower($method));
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
        $this->addRoute('get', $url, $locale, $controller, $method, $name);
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
        $this->addRoute('post', $url, $locale, $controller, $method, $name);
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
     * @param  string  $from
     * @param  string  $to
     * @return void
     *
     */
    public function redirect(string $from, string $to): void
    {
        $redirect = new Redirect($from, $to);
        self::$redirects->push($redirect);
    }

    /**
     *
     * Dispatch the route detection and execute and render matching route
     *
     * @return void
     * @throws DatabaseException
     * @throws EntryException
     * @throws FileException
     * @throws FilesystemException
     * @throws JsonException
     * @throws LoaderError
     * @throws RouteReturnException
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws ACLException
     * @throws PermissionException
     * @throws SodiumException
     *
     */
    public static function dispatch(): void
    {
        $uri = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

        if ($uri === '') {
            $uri = '/';
        }

        if (!empty(self::$root) && self::$root !== '/') {
            self::$root = rtrim(self::$root, '/');

            if (self::$root === $uri) {
                $uri = '/';
            } else {
                // Remove the root directory from uri, remove only the first occurrence
                $uri = substr_replace($uri, '', strpos($uri, self::$root), strlen(self::$root));
            }
        }

        if ($uri === '/') {
            $entry = Entry::getHomepageEntry(Sail::siteId(), Locale::$current);
        } else {
            $entry = Entry::findByURL($uri);
        }

        if ($entry) {
            $response = Response::html();

            if (!isset($entry->template)) {
                throw new EntryException(Entry::TEMPLATE_NOT_SET);
            }

            Locale::setCurrent($entry->locale);

            $response->template = $entry->template;
            $response->set('entry', $entry);
            $response->render();
            die();
        }

        // Not found, check if we have something defined
        $method = strtolower($_SERVER['REQUEST_METHOD']);

        // Method based routes
        foreach (self::$routes->get($method) as $num => $route) {
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
        foreach (self::$routes->get('any') as $num => $route) {
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
        self::$redirects->each(function ($key, $redirect) use ($uri)
        {
            $redirect->matchAndExecute($uri);
        });

        $response = Response::html();
        $response->set('year', date('Y'));

        if ($uri === '/') {
            Debug::route('GET', '/', 'system:home', 'default homepage');

            // If we are here, the cms did not pick up on the homepage, show built in homepage
            $response->template = 'welcome_default';
            $response->render();
            die();
        }

        Debug::route('GET', '/', 'system:not_found', '404');
        $response->template = '404';
        $response->render();
        die();
    }

    /**
     *
     * Find the alternate routes for the given name
     *
     * @param  Route  $route
     * @return Collection
     */
    public function alternate(Route $route): Collection
    {
        $alternateRoutes = Collection::init();
        $method = $route->getHTTPMethod();

        self::$routes->get($method)->each(static function ($key, $value) use (&$alternateRoutes, $route)
        {
            if ($value->getName() === $route->getName() && $value->getLocale() !== Locale::current()) {
                $alternateRoutes->push($value);
            }
        });

        return $alternateRoutes;
    }

    /**
     *
     * Get a route by name, method and locale, optionally process arguments to replace dynamic placeholders
     *
     * @param  string            $name
     * @param  string            $method
     * @param  string            $locale
     * @param  Collection|array  $arguments
     * @return string|null
     *
     */
    public static function getBy(string $name, string $method, string $locale = 'en', Collection|array $arguments = []): ?string
    {
        $route = null;
        self::$routes->get($method)->each(static function ($key, $value) use (&$route, $name, $locale)
        {
            if ($value->getName() === $name && $value->getLocale() === $locale) {
                $route = $value;
            }
        });

        if ($route) {
            return $route->getURL(...$arguments);
        }

        return null;
    }

    /**
     *
     * Get all routes that match name and method, optionally process all dynamics placeholders
     *
     * @param  string            $name
     * @param  string            $method
     * @param  Collection|array  $arguments
     * @return Collection
     *
     */
    public static function getAllBy(string $name, string $method, Collection|array $arguments = []): Collection
    {
        $routes = Collection::init();
        self::$routes->get($method)->each(static function ($key, $value) use (&$routes, $name)
        {
            if ($value->getName() === $name) {
                $routes->pushKeyValue($value->getLocale(), $value);
            }
        });

        if ($routes->length > 0) {
            $out = Collection::init();

            foreach ($routes->unwrap() as $key => $value) {
                $out->pushKeyValue($key, $value->getURL(...$arguments));
            }

            return $out;
        }

        return Collection::init();
    }

    /**
     *
     * Get all routes with the given name
     *
     * @param  string  $name
     * @return Collection
     *
     */
    public function routesByName(string $name): Collection
    {
        $routes = Collection::init();
        $methods = ['get', 'post', 'delete', 'put', 'any'];

        foreach ($methods as $method) {
            self::$routes->get($method)->each(static function ($key, $value) use (&$routes, $name)
            {
                if ($value->getName() === $name) {
                    $routes->push($value);
                }
            });
        }

        return $routes;
    }

    /**
     *
     * Register a route for the clockwork debug tool
     *
     * @return void
     *
     */
    public static function addClockworkSupport(): void
    {
        $router = new static();
        $router->addRoute(
            'any',
            '/__clockwork/:any',
            'en',
            DebugController::class,
            'handleClockWork'
        );
    }

    // -------------------------------------------------- Private -------------------------------------------------- //

    private function addRoute(string $method, string $url, string $locale, AppController|string $controller, string $callback, string $name = ''): void
    {
        // Trace the registerer of the route
        $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 3);
        $class = $trace[2]['class'];

        if ($trace[2]['function'] !== '{closure}' && !str_contains($class, 'Tests\routerTest')) {
            if ($trace[2]['function'] !== 'routes' || !is_subclass_of($class, AppContainer::class)) {
                // Illegal call, this should only be called from the routes method in a container
                throw new \RuntimeException('Cannot add routes from anything other than an AppContainer using the routes method.', 0403);
            }
        }

        if (is_string($controller)) {
            $controller = new $controller();
        }

        // Since no name was given, use the url
        if (empty($name)) {
            $name = str_replace('/', '_', $url);
        }

        if ($trace[2]['function'] !== '{closure}' && !str_contains($class, 'Tests\routerTest')) {
            Register::registerRoute($method, $url, $class);
        }

        $route = new Route($name, $url, $locale, $controller, $callback, $method);
        self::$routes->get(strtolower($method))->push($route);
    }
}