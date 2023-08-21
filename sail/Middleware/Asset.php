<?php

namespace SailCMS\Middleware;

use SailCMS\Contracts\AppMiddleware;
use SailCMS\Types\MiddlewareType;

abstract class Asset implements AppMiddleware
{
    public const OnUpload = 'OnUpload';
    public const BeforeProcess = 'BeforeProcess';
    public const AfterProcess = 'AfterProcess';

    abstract public function process(Data $data): Data;

    public function type(): MiddlewareType
    {
        return MiddlewareType::ASSET;
    }
}