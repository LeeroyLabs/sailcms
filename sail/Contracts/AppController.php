<?php

namespace SailCMS\Contracts;

use SailCMS\Http\Request;
use SailCMS\Http\Response;
use SailCMS\Locale;
use SailCMS\Routing\Router;

abstract class AppController
{
    protected Router $router;
    protected Request $request;
    protected Response $response;
    protected Locale $locale;

    public function __construct()
    {
        $this->router = new Router();
        $this->request = new Request();
        $this->response = new Response('text/html');
        $this->locale = new Locale();
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