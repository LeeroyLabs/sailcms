<?php

namespace SailCMS\Middleware;

use SailCMS\Contracts\AppMiddleware;
use SailCMS\Types\MiddlewareType;

abstract class Login implements AppMiddleware
{
    public const LogIn = 'LogIn';
    public const ForgotPassword = 'ForgotPassword';

    abstract public function process(Data $data): Data;

    public function type(): MiddlewareType
    {
        return MiddlewareType::LOGIN;
    }
}