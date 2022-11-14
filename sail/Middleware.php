<?php

namespace SailCMS;

use SailCMS\Middleware\Data;
use \SailCMS\Types\MiddlewareType;
use \SailCMS\Contracts\AppMiddleware as MW;

class Middleware
{
    private static array $middlewares = [];

    /**
     *
     * Register a middleware
     *
     * @param  MW  $middleware
     * @return void
     */
    public static function register(MW $middleware): void
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 3);

        //print_r($trace);

        Register::registerMiddleware($middleware);
        static::$middlewares[$middleware->type()->value][] = $middleware;
    }

    /**
     *
     * Execute the middlewares for the given type with given data (event and data)
     *
     * @param  MiddlewareType  $type
     * @param  Data            $data
     * @return Data
     *
     */
    public static function execute(MiddlewareType $type, Data $data): Data
    {
        $mws = static::$middlewares[$type->value] ?? [];

        foreach ($mws as $mw) {
            $data = $mw->process($data);
        }

        return $data;
    }
}