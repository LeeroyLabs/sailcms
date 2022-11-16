<?php

namespace SailCMS;

use Clockwork\Support\Vanilla\Clockwork;
use SailCMS\Debug\DbParser;
use SailCMS\Types\QueryOptions;

class Debug
{
    private static bool $useRay = false;

    /**
     *
     * Log messages
     *
     * @param  mixed  ...$messages
     * @return void
     *
     */
    public static function log(mixed ...$messages): void
    {
        if (env('debug', 'off') === 'on') {
            Clockwork::instance(...$messages);
        }
    }

    /**
     *
     * Log an info message with available context
     *
     * @param  string            $message
     * @param  Collection|array  $context
     * @return void
     *
     */
    public static function info(string $message, Collection|array $context = []): void
    {
        if (!is_array($context)) {
            $context = $context->unwrap();
        }

        Clockwork::instance()?->log($message, $context);
    }

    /**
     *
     * Log a warning message with available context
     *
     * @param  string            $message
     * @param  Collection|array  $context
     * @return void
     *
     */
    public static function warn(string $message, Collection|array $context = []): void
    {
        if (!is_array($context)) {
            $context = $context->unwrap();
        }

        Clockwork::instance()?->warning($message, $context);
    }

    /**
     *
     * Log a fatal error message with available context
     *
     * @param  string            $message
     * @param  Collection|array  $context
     * @return void
     *
     */
    public static function error(string $message, Collection|array $context = []): void
    {
        if (!is_array($context)) {
            $context = $context->unwrap();
        }

        Clockwork::instance()?->error($message, $context);
    }

    /**
     *
     * Start a query
     *
     * @return float
     *
     */
    public static function startQuery(): float
    {
        return microtime(true);
    }

    /**
     *
     * End a query
     *
     * @param  array  $config
     * @return void
     *
     */
    public static function endQuery(array $config): void
    {
        if (env('debug', 'off') === 'on') {
            $dbg = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 3)[2];

            $file = basename($dbg['file']);
            $line = $dbg['line'];

            if (!is_array($config['query'])) {
                $config['query'] = $config['query']->unwrap();
            }

            $data = [
                'model' => $config['model'],
                'connection' => 'mongodb',
                'file' => $file,
                'line' => $line
            ];

            if ($config['operation'] !== 'aggregate' && $config['operation'] !== 'bulkWrite') {
                $parsedQuery = DbParser::parseQuery($config);
                $config['query'] = [];
            } elseif ($config['operation'] === 'bulkWrite') {
                $parsedQuery = $config['operation'];
                $config['query'] = 'Multiple unparsed queries';
            } else {
                $parsedQuery = $config['operation'];
                $config['query'] = $config['pipeline'];
            }

            Clockwork::instance()?->addDatabaseQuery(
                $parsedQuery,
                $config['query'] ?? [],
                (microtime(true) - $config['time']) * 1000,
                $data
            );
        }
    }

    /**
     *
     * Debug routing
     *
     * @param  string  $method
     * @param  string  $url
     * @param  string  $call
     * @param  string  $name
     * @return void
     *
     */
    public static function route(string $method, string $url, string $call, string $name): void
    {
        Clockwork::instance()?->addRoute($method, $url, $call, ['name' => $name]);
    }

    /**
     *
     * Register a view and it's context
     *
     * @param  string  $file
     * @param  array   $context
     * @param  float   $time
     * @return void
     */
    public static function view(string $file, array $context, float $time): void
    {
        Clockwork::instance()?->addView(
            $file . '.twig',
            $context,
            [
                'name' => $file,
                'duration' => (microtime(true) - $time) * 1000
            ]
        );
    }

    /**
     *
     * Start a timed event (with optional color and name if not unique)
     *
     * @param  string  $message
     * @param  string  $color
     * @param  string  $name
     * @return void
     *
     */
    public static function eventStart(string $message, string $color = 'blue', string $name = ''): void
    {
        if ($name === '') {
            Clockwork::instance()?->event($message)->color($color)->begin();
            return;
        }

        Clockwork::instance()?->event($message)->color($color)->name($name)->begin();
    }

    /**
     *
     * End a started event, if no name was provided, use the message to close
     *
     * @param  string  $name
     * @return void
     *
     */
    public static function eventEnd(string $name): void
    {
        Clockwork::instance()?->event($name)->end();
    }

    /**
     *
     * Dump & die the given variables and where the dump was made
     *
     * @return void
     *
     */
    public static function dd(): void
    {
        $variables = func_get_args();
        $callstack = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 5);
        $directCaller = $callstack[0];

        if (count($callstack) > 1) {
            $directCaller = $callstack[1];
        }

        self::printOut($variables, $directCaller);
        die();
    }

    /**
     *
     * Output variables to ray
     *
     * @param  mixed  ...$variables
     * @return void
     *
     */
    public static function ray(mixed ...$variables): void
    {
        $level = 'debug';

        if (count($variables) > 1) {
            $level = $variables[count($variables) - 1];
            unset($variables[count($variables) - 1]);
        }

        switch ($level) {
            default:
            case 'debug':
            case 'log':
                $levelColor = 'blue';
                break;

            case 'success':
                $levelColor = 'green';
                break;

            case 'warning':
            case 'warn':
                $levelColor = 'yellow';
                break;

            case 'critical':
            case 'error':
                $levelColor = 'red';
                break;
        }


        if (setting('logging.useRay', false)) {
            ray(...$variables)->color($levelColor);
        }
    }

    /**
     *
     * Dump given variables with where the dump was made
     *
     * @return void
     *
     */
    public static function dump(): void
    {
        $variables = func_get_args();
        $callstack = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 5);
        $directCaller = $callstack[0];

        if (count($callstack) > 1) {
            $directCaller = $callstack[1];
        }

        static::printOut($variables, $directCaller);
    }

    private static function printOut(array|object $variables, array $caller): void
    {
        $dir = basename(dirname($caller['file']));
        $name = basename($caller['file']);
        $line = $caller['line'];

        if (setting('logging.useRay', false)) {
            ray(...$variables);
        }

        echo '<div style="display: flex; border-radius: 8px; background-color: #18171B; padding: 6px 10px;">';
        echo '<div style="flex-grow: 1;">';
        dump(...$variables);

        $css = 'padding: 10px 5px; color: #ffffff; font-family: Arial,serif; font-size: 14px;';
        echo '</div><div style="' . $css . '">' . $dir . '/' . $name . ':' . $line . '</div></div>';
    }
}