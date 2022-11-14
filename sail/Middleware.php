<?php

namespace SailCMS;

use SailCMS\Contracts\AppContainer;
use SailCMS\Contracts\AppModule;
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
        $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 2);
        $class = $trace[1]['class'];
        $func = $trace[1]['function'];

        if ($func !== 'middleware' || (!is_subclass_of($class, AppContainer::class) && !is_subclass_of($class, AppModule::class))) {
            throw new \RuntimeException('Cannot register middlewares from anything other than a AppContainer using the middleware method.', 0403);
        }

        Register::registerMiddleware($middleware, $class);
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