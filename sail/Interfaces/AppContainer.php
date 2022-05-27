<?php

namespace SailCMS\Interfaces;

use SailCMS\Routing\Router;
use SailCMS\Types\ContainerInformation;

abstract class AppContainer
{
    protected Router $router;

    abstract public function info(): ContainerInformation;

    abstract public function routes(): void;

    public function __construct()
    {
        $this->router = new Router();
    }
}