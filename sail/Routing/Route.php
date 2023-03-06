<?php

namespace SailCMS\Routing;

use League\Flysystem\FilesystemException;
use SailCMS\Collection;
use SailCMS\Debug;
use SailCMS\DI;
use SailCMS\Http\Response;
use SailCMS\Contracts\AppController;
use SailCMS\Locale;
use SailCMS\Models\User;
use SailCMS\Text;

final class Route
{
    private string $name;
    private string $locale;
    private string $url;
    private string $HTTPMethod;
    private AppController|string $controller;
    private string $method;
    private bool $secure;

    private static array $patterns = [
        ':any' => '([a-zA-Z0-9\-]+)',
        ':string' => '([a-zA-Z]+)',
        ':id' => '([a-z0-9]{24})',
        ':num' => '([0-9]+)',
        ':all' => '(.*)'
    ];

    public function __construct(string $name, string $url, string $locale, AppController|string $controller, string $method, string $httpMethod = 'get', bool $secure = false)
    {
        if (is_string($controller)) {
            $controller = DI::resolve($controller);
        }

        $this->name = str_replace('//', '/', $name);
        $this->locale = $locale;
        $this->url = $url;
        $this->HTTPMethod = $httpMethod;
        $this->controller = $controller;
        $this->method = $method;
        $this->secure = $secure;
    }

    /**
     *
     * Try to match the route to the URL, if it does, execute the route
     *
     * @param  string  $url
     * @return Response|null
     * @throws FilesystemException
     *
     */
    public function matches(string $url): ?Response
    {
        $searches = array_keys(self::$patterns);
        $replaces = array_values(self::$patterns);

        // Check if route is defined without regex
        if ($url === $this->url) {
            Locale::setCurrent($this->locale);

            if ($this->secure && !User::$currentUser) {
                // Secure URL but user is not logged in
                Debug::route('GET', '/', 'system:forbidden', '403');

                $response = Response::html();
                $response->set('year', date('Y'));
                $response->template = '403';
                $response->render();
                return $response;
            }

            if (is_string($this->controller)) {
                $instance = DI::resolve($this->controller);
                $class = $this->controller;
            } else {
                $class = get_class($this->controller);
                $instance = $this->controller;
            }

            $instance->setRoute($this);

            Debug::route($this->method, $url, $class . ':' . $this->method, Text::slugify($url));

            call_user_func_array([$instance, $this->method], []);
            return $instance->getResponse();
        }

        if (str_contains($this->url, ':')) {
            $route = str_replace($searches, $replaces, $this->url);

            if (preg_match('#^' . $route . '$#', $url, $matched)) {
                unset($matched[0]);
                $matches = array_values($matched);

                Locale::setCurrent($this->locale);

                if (is_string($this->controller)) {
                    $instance = DI::resolve($this->controller);
                    $class = $this->controller;
                } else {
                    $class = get_class($this->controller);
                    $instance = $this->controller;
                }

                $instance->setRoute($this);

                if (!str_contains($url, '__clockwork')) {
                    Debug::route($this->method, $url, $class . ':' . $this->method, Text::slugify($url));
                }

                call_user_func_array([$instance, $this->method], $matches);
                return $instance->getResponse();
            }
        }

        return null;
    }

    /**
     *
     * Get route name
     *
     * @return string
     *
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     *
     * Get route method
     *
     * @return string
     *
     */
    public function getHTTPMethod(): string
    {
        return $this->HTTPMethod;
    }

    /**
     *
     * Get Locale
     *
     * @return string
     *
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     *
     * Get the url, if arguments passed, dynamic elements are replaced
     *
     * @param  string  ...$arguments
     * @return string
     */
    public function getURL(string ...$arguments): string
    {
        $re = "/(:[a-z]+)/i";

        $url = explode('/', $this->url);
        $fragmentNumber = 0;

        foreach ($url as $num => $fragment) {
            if (preg_match_all($re, $fragment, $matches, PREG_SET_ORDER, 0)) {
                $url[$num] = $arguments[$fragmentNumber];
                $fragmentNumber++;
            }
        }

        return implode('/', $url);
    }
}