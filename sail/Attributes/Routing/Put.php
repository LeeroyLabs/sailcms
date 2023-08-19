<?php

namespace SailCMS\Attributes\Routing;

#[\Attribute]
class Put extends HttpMethod
{
    public function __construct(public array $routes, string $name)
    {
        parent::__construct('put', $routes, $name);
    }
}