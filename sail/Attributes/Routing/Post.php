<?php

namespace SailCMS\Attributes\Routing;

#[\Attribute]
class Post extends HttpMethod
{
    public function __construct(public array $routes, string $name)
    {
        parent::__construct('post', $routes, $name);
    }
}