<?php

namespace SailCMS;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\Logger;

final class Log
{
    private static Logger $logger;

    public static function init()
    {
        $handlers = setting('logging.adapters', []);
        self::$logger = new Logger(setting('logging.loggerName', 'sailcms'));

        foreach ($handlers as $handler) {
            self::$logger->pushHandler(
                new $handler(setting('logging.minLevel', Level::Debug), setting('logging.bubble', true))
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
        self::$logger->pushHandler($adapter);
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
        return self::$logger;
    }

    public static function debug(string $message, Collection|array $context): void
    {
        self::log('debug', $message, $context);
    }

    public static function info(string $message, Collection|array $context): void
    {
        self::log('info', $message, $context);
    }

    public static function notice(string $message, Collection|array $context): void
    {
        self::log('notice', $message, $context);
    }

    public static function warning(string $message, Collection|array $context): void
    {
        self::log('warning', $message, $context);
    }

    public static function error(string $message, Collection|array $context): void
    {
        self::log('error', $message, $context);
    }

    public static function critical(string $message, Collection|array $context): void
    {
        self::log('critical', $message, $context);
    }

    public static function alert(string $message, Collection|array $context): void
    {
        self::log('alert', $message, $context);
    }

    public static function emergency(string $message, Collection|array $context): void
    {
        self::log('emergency', $message, $context);
    }

    private static function log(string $level, string $message, Collection|array $context): void
    {
        if (!is_array($context)) {
            $context = $context->unwrap();
        }

        switch ($level) {
            default:
            case 'debug':
                self::$logger->debug($message, $context);
                break;

            case 'info':
                self::$logger->info($message, $context);
                break;

            case 'notice':
                self::$logger->notice($message, $context);
                break;

            case 'warning':
                self::$logger->warning($message, $context);
                break;

            case 'error':
                self::$logger->error($message, $context);
                break;

            case 'critical':
                self::$logger->critical($message, $context);
                break;

            case 'alert':
                self::$logger->alert($message, $context);
                break;

            case 'emergency':
                self::$logger->emergency($message, $context);
                break;
        }
    }
}