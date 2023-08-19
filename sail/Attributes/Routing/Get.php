<?php

namespace SailCMS\Attributes\Routing;

#[\Attribute]
class Get extends HttpMethod
{
    public function __construct(public array $routes, string $name)
    {
        parent::__construct('get', $routes, $name);
    }
}