<?php

// Add env function only if it does not exist already
// This enables developers to use pieces of Laravel without
// having a deadlock collision for this function
if (!function_exists('env')) {
    /**
     *
     * Get a environment variable (set a default if not set)
     *
     * @param  string  $var
     * @param  mixed   $default
     * @return mixed
     *
     */
    function env(string $var, mixed $default = null): mixed
    {
        return $_ENV[strtoupper($var)] ?? $default;
    }
}

/**
 *
 * Get a setting by the dot notation path or set a default value if not set
 *
 * @param  string  $var
 * @param  mixed   $default
 * @return mixed
 *
 */
function setting(string $var, mixed $default = null): mixed
{
    return $_ENV['SETTINGS']->get($var, $default);
}