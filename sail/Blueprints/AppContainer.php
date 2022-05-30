<?php

namespace SailCMS\Blueprints;

use SailCMS\Routing\Router;
use SailCMS\Types\ContainerInformation;

abstract class AppContainer
{
    protected Router $router;

    abstract public function info(): ContainerInformation;

    abstract public function routes(): void;

    abstract public function configureSearch(): void;

    public function __construct()
    {
        $this->router = new Router();
    }
}