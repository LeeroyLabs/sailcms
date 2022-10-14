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
     * @return void
     *
     */
    public static function ray(): void
    {
        $variables = func_get_args();

        if ($_ENV['SETTINGS']->get('logging.useRay')) {
            ray(...$variables);
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