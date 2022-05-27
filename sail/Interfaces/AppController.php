<?php

namespace SailCMS\Interfaces;

use SailCMS\Routing\Router;

abstract class AppController
{
    protected Router $router;

    public function __construct()
    {
        $this->router = new Router();
    }

    // TODO : IMPLEMENT BASICS
}