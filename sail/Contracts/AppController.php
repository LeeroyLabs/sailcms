<?php

namespace SailCMS\Contracts;

use SailCMS\Http\Request;
use SailCMS\Routing\Router;

abstract class AppController
{
    protected Router $router;
    protected Request $request;

    public function __construct()
    {
        $this->router = new Router();
        $this->request = new Request();
    }

    // TODO : IMPLEMENT BASICS
}