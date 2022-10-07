<?php

namespace SailCMS\Contracts;

use SailCMS\Routing\Router;
use SailCMS\Types\ContainerInformation;

abstract class AppContainer
{
    protected Router $router;

    protected string $currentApiURL = '/api/v3';

    abstract public function info(): ContainerInformation;

    abstract public function routes(): void;

    abstract public function configureSearch(): void;

    abstract public function graphql(): void;

    abstract public function middleware(): void;

    public function __construct()
    {
        $this->router = new Router();
    }
}