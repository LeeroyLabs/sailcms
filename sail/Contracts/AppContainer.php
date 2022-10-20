<?php

namespace SailCMS\Contracts;

use SailCMS\Collection;
use SailCMS\Routing\Router;
use SailCMS\Types\ContainerInformation;

abstract class AppContainer
{
    protected Router $router;

    abstract public function info(): ContainerInformation;

    abstract public function routes(): void;

    abstract public function configureSearch(): void;

    abstract public function graphql(): void;

    abstract public function middleware(): void;

    abstract public function cli(): Collection;

    abstract public function permissions(): Collection;

    public function __construct()
    {
        $this->router = new Router();
    }
}