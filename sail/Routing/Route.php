<?php

namespace SailCMS\Routing;

use League\Flysystem\FilesystemException;
use SailCMS\Debug;
use SailCMS\Http\Response;
use SailCMS\Contracts\AppController;
use SailCMS\Locale;
use SailCMS\Text;

class Route
{
    private string $name;
    private string $locale;
    private string $url;
    private AppController|string $controller;
    private string $method;

    private static array $patterns = [
        ':any' => '([a-zA-Z0-9\-]+)',
        ':string' => '([a-zA-Z]+)',
        ':id' => '([0-9]{24})',
        ':num' => '([0-9]+)',
        ':all' => '(.*)'
    ];

    public function __construct(string $name, string $url, string $locale, AppController|string $controller, string $method)
    {
        if (is_string($controller)) {
            $controller = new $controller();
        }

        $this->name = str_replace('//', '/', $method . '_' . $name);
        $this->locale = $locale;
        $this->url = $url;
        $this->controller = $controller;
        $this->method = $method;
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
        $searches = array_keys(static::$patterns);
        $replaces = array_values(static::$patterns);

        // Check if route is defined without regex
        if ($url === $this->url) {
            Locale::setCurrent($this->locale);

            if (is_string($this->controller)) {
                $instance = new $this->controller();
                $class = $this->controller;
            } else {
                $instance = $this->controller;
                $class = get_class($this->controller);
            }

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
                    $instance = new $this->controller();
                    $class = $this->controller;
                } else {
                    $instance = $this->controller;
                    $class = get_class($this->controller);
                }

                if (!str_contains($url, '__clockwork')) {
                    Debug::route($this->method, $url, $class . ':' . $this->method, Text::slugify($url));
                }

                call_user_func_array([$instance, $this->method], $matches);
                return $instance->getResponse();
            }
        }

        return null;
    }
}