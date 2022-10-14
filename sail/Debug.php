<?php

namespace SailCMS;

class Debug
{
    private static bool $useRay = false;

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


        if ($_ENV['SETTINGS']->get('logging.useRay')) {
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

        if ($_ENV['SETTINGS']->get('logging.useRay')) {
            ray(...$variables);
        }

        echo '<div style="display: flex; border-radius: 8px; background-color: #18171B; padding: 6px 10px;">';
        echo '<div style="flex-grow: 1;">';
        dump(...$variables);

        $css = 'padding: 10px 5px; color: #ffffff; font-family: Arial,serif; font-size: 14px;';
        echo '</div><div style="' . $css . '">' . $dir . '/' . $name . ':' . $line . '</div></div>';
    }
}