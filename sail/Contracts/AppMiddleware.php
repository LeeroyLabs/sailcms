<?php

namespace SailCMS\Contracts;

use SailCMS\Middleware\Data;
use SailCMS\Types\MiddlewareType;

interface AppMiddleware
{
    public function type(): MiddlewareType;

    public function process(Data $data): Data;
}