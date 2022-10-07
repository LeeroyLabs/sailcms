<?php

namespace SailCMS\Middleware;

use SailCMS\Contracts\AppMiddleware;
use SailCMS\Types\MiddlewareType;

abstract class GraphQL implements AppMiddleware
{
    public const BeforeQuery = 'BeforeQuery';
    public const AfterQuery = 'AfterQuery';
    public const BeforeMutation = 'BeforeMutation';
    public const AfterMutation = 'AfterMutation';

    abstract public function process(Data $data): Data;

    public function type(): MiddlewareType
    {
        return MiddlewareType::GRAPHQL;
    }
}