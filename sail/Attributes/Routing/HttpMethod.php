<?php

namespace SailCMS\Attributes\Routing;

class HttpMethod
{
    public string $method;
    public string $name;
    public array $routes;

    public function __construct(string $method, array $routes, string $name)
    {
        $this->method = $method;
        $this->name = $name;
        $this->routes = $routes;
    }
}