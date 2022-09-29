<?php

namespace SailCMS\Middleware;

use SailCMS\Contracts\Middleware;
use SailCMS\Types\MiddlewareType;

abstract class Http implements Middleware
{
    public const BeforeGraphQL = 'BeforeGraphQL';
    public const AfterGraphQL = 'BeforeGraphQL';
    public const BeforeRoute = 'BeforeRoute';
    public const BeforeShutdown = 'BeforeShutdown';
    public const BeforeRender = 'BeforeRender';
    public const AfterRender = 'AfterRender';

    public function process(Data $data): Data
    {
        // TODO: Implement process() method.
    }

    public function type(): MiddlewareType
    {
        return MiddlewareType::HTTP;
    }
}