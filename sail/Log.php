<?php

namespace SailCMS;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;

class Log
{
    private static Logger $logger;

    public static function init()
    {
        $handlers = $_ENV['SETTINGS']->get('logging.adapters');

        static::$logger = new Logger($_ENV['SETTINGS']->get('logging.loggerName'));

        foreach ($handlers as $handler) {
            static::$logger->pushHandler(
                new $handler($_ENV['SETTINGS']->get('logging.minLevel'), $_ENV['SETTINGS']->get('logging.bubble'))
            );
        }
    }

    /**
     *
     * Add an adapter that requires configuration
     *
     * @param  AbstractProcessingHandler  $adapter
     * @return void
     *
     */
    public static function addAdapter(AbstractProcessingHandler $adapter): void
    {
        static::$logger->pushHandler($adapter);
    }

    /**
     *
     * Get the access to the Monolog instance
     *
     * @return Logger
     *
     */
    public static function logger(): Logger
    {
        return static::$logger;
    }

    public static function debug(string $message, Collection|array $context): void
    {
        static::log('debug', $message, $context);
    }

    public static function info(string $message, Collection|array $context): void
    {
        static::log('info', $message, $context);
    }

    public static function notice(string $message, Collection|array $context): void
    {
        static::log('notice', $message, $context);
    }

    public static function warning(string $message, Collection|array $context): void
    {
        static::log('warning', $message, $context);
    }

    public static function error(string $message, Collection|array $context): void
    {
        static::log('error', $message, $context);
    }

    public static function critical(string $message, Collection|array $context): void
    {
        static::log('critical', $message, $context);
    }

    public static function alert(string $message, Collection|array $context): void
    {
        static::log('alert', $message, $context);
    }

    public static function emergency(string $message, Collection|array $context): void
    {
        static::log('emergency', $message, $context);
    }

    private static function log(string $level, string $message, Collection|array $context): void
    {
        if (!is_array($context)) {
            $context = $context->unwrap();
        }

        switch ($level) {
            default:
            case 'debug':
                static::$logger->debug($message, $context);
                break;

            case 'info':
                static::$logger->info($message, $context);
                break;

            case 'notice':
                static::$logger->notice($message, $context);
                break;

            case 'warning':
                static::$logger->warning($message, $context);
                break;

            case 'error':
                static::$logger->error($message, $context);
                break;

            case 'critical':
                static::$logger->critical($message, $context);
                break;

            case 'alert':
                static::$logger->alert($message, $context);
                break;

            case 'emergency':
                static::$logger->emergency($message, $context);
                break;
        }
    }
}