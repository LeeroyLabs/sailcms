<?php

namespace SailCMS\Attributes\Routing;

#[\Attribute]
class Delete extends HttpMethod
{
    public function __construct(public array $routes, string $name)
    {
        parent::__construct('delete', $routes, $name);
    }
}