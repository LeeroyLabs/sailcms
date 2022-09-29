<?php

namespace SailCMS;

use SailCMS\Middleware\Data;
use \SailCMS\Types\MiddlewareType;
use \SailCMS\Contracts\Middleware as MW;

class Middleware
{
    private static array $middlewares = [];

    /**
     *
     * Register a middleware
     *
     * @param MW $middleware
     * @return void
     */
    public static function register(MW $middleware): void
    {
        static::$middlewares[$middleware->type()->value] = $middleware;
    }

    /**
     *
     * Execute the middlewares for the given type with given data (event and data)
     *
     * @param MiddlewareType $type
     * @param Data $data
     * @return mixed
     *
     */
    public static function execute(MiddlewareType $type, Data $data): mixed
    {
        $mws = static::$middlewares[$type->value] ?? [];

        foreach ($mws as $mw) {
            $data = $mw->process($data);
        }

        return $data->data;
    }
}