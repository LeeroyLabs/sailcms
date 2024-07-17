<?php

namespace SailCMS\Contracts;

use SailCMS\Collection;
use SailCMS\GraphQL\Context;
use SailCMS\Http\Request;
use SailCMS\Http\Response;
use SailCMS\Locale;
use SailCMS\Routing\Route;
use SailCMS\Routing\Router;

abstract class AppController
{
    protected Router $router;
    protected Route $route;
    protected Request $request;
    protected Response $response;
    protected Locale $locale;
    private Collection $arguments;
    private ?Context $graphqlContext = null;

    public function __construct()
    {
        $this->router = new Router();
        $this->request = new Request();
        $this->response = new Response('text/html');
        $this->locale = new Locale();
    }

    /**
     *
     * Set the available arguments for a given GraphQL call
     *
     * @param  array|Collection  $arguments
     * @return void
     *
     */
    public function setArguments(array|Collection $arguments): void
    {
        if (is_array($arguments)) {
            $arguments = new Collection($arguments);
        }

        $this->arguments = $arguments;
    }

    /**
     *
     * Set the current GraphQL Context
     *
     * @param  Context  $context
     * @return void
     *
     */
    public function setContext(Context $context): void
    {
        $this->graphqlContext = $context;
    }

    /**
     *
     * Get an argument that was passed to a GraphQL call
     *
     * @param  string      $field
     * @param  mixed|null  $default
     * @return mixed
     *
     */
    protected function arg(string $field, mixed $default = null): mixed
    {
        return $this->arguments->get($field, $default);
    }

    /**
     *
     * Return the current GraphQL Context
     *
     * @return Context
     *
     */
    protected function context(): Context
    {
        if ($this->graphqlContext) {
            return $this->graphqlContext;
        }

        return new Context();
    }

    /**
     *
     * Get current locale
     *
     * @return string
     *
     */
    protected function currentLocale(): string
    {
        return Locale::current();
    }

    /**
     *
     * Get the response for output rendering
     *
     * @return Response
     *
     */
    public function getResponse(): Response
    {
        return $this->response;
    }

    /**
     *
     * Set the current route
     *
     * @param  Route  $route
     * @return void
     *
     */
    public function setRoute(Route $route): void
    {
        $this->route = $route;
    }

    /**
     *
     * Redirect quickly
     *
     * @param  string  $url
     * @param  bool    $permanent
     * @return void
     *
     */
    public function redirect(string $url, bool $permanent = false): void
    {
        if ($permanent) {
            header("HTTP/1.1 301 Moved Permanently");
        }

        header('location: ' . $url);
        die();
    }
}