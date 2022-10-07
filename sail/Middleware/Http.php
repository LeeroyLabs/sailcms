<?php

namespace SailCMS\Middleware;

use SailCMS\Contracts\AppMiddleware;
use SailCMS\Types\MiddlewareType;

abstract class Http implements AppMiddleware
{
    public const BeforeRoute = 'BeforeRoute';
    public const BeforeRender = 'BeforeRender';
    public const AfterRender = 'AfterRender';

    abstract public function process(Data $data): Data;

    public function type(): MiddlewareType
    {
        return MiddlewareType::HTTP;
    }
}