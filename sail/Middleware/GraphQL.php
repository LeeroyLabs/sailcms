<?php

namespace SailCMS\Middleware;

use SailCMS\Contracts\Middleware;
use SailCMS\Types\MiddlewareType;

abstract class GraphQL implements Middleware
{
    public const BeforeQuery = 'BeforeQuery';
    public const AfterQuery = 'AfterQuery';
    public const BeforeMutation = 'BeforeMutation';
    public const AfterMutation = 'AfterMutation';

    public function process(Data $data): Data
    {
        // TODO: Implement process() method.
    }

    public function type(): MiddlewareType
    {
        return MiddlewareType::GRAPHQL;
    }
}