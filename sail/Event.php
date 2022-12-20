<?php

namespace SailCMS;

final class Event
{
    private static Collection $registered;

    /**
     *
     * Register a handler for given event name
     *
     * @param  string  $event
     * @param  string  $class
     * @param  string  $method
     * @return void
     *
     */
    public static function register(string $event, string $class, string $method): void
    {
        if (!isset(self::$registered)) {
            self::$registered = Collection::init();
        }

        self::$registered->{$event} = new Collection(['class' => new $class(), 'method' => $method]);
    }

    /**
     *
     * Dispatch an event to all it's handlers
     *
     * @param  string  $event
     * @param  mixed   $data
     * @return void
     *
     */
    public static function dispatch(string $event, mixed $data): void
    {
        if (isset(self::$registered) && self::$registered->get($event)) {
            self::$registered->get($event)->each(static function ($key, $value) use ($event, $data)
            {
                $instance = $value->get('class');
                $method = $value->get('method');

                if ($instance && is_object($instance) && method_exists($instance, $method)) {
                    $instance->{$method}($event, $data);
                }
            });
        }
    }
}