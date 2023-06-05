<?php

namespace SailCMS\Middleware;

use SailCMS\Contracts\AppMiddleware;
use SailCMS\Types\MiddlewareType;

abstract class Entry implements AppMiddleware
{
    public const BeforeCreate = 'BeforeCreate';
    public const BeforeUpdate = 'BeforeUpdate';

    abstract public function process(Data $data): Data;

    public function type(): MiddlewareType
    {
        return MiddlewareType::ENTRY;
    }
}