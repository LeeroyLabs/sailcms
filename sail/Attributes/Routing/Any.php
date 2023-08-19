<?php

namespace SailCMS\Attributes\Routing;

#[\Attribute]
class Any extends HttpMethod
{
    public function __construct(public array $routes, string $name)
    {
        parent::__construct('any', $routes, $name);
    }
}