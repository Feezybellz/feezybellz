<?php

namespace Framework\Core\Logging;

/**
 * Static Facade for the Logger
 * * @method static void emergency(string $message, array $context = [])
 * @method static void alert(string $message, array $context = [])
 * @method static void critical(string $message, array $context = [])
 * @method static void error(string $message, array $context = [])
 * @method static void warning(string $message, array $context = [])
 * @method static void notice(string $message, array $context = [])
 * @method static void info(string $message, array $context = [])
 * @method static void debug(string $message, array $context = [])
 */
class Log
{
    protected static $logger = null;

    /**
     * Get the singleton Logger instance.
     */
    public static function getLogger(): Logger
    {
        if (self::$logger === null) {
            self::$logger = new Logger();
        }
        return self::$logger;
    }

    /**
     * Pass any static calls dynamically to the underlying Logger instance.
     */
    public static function __callStatic($method, $args)
    {
        return self::getLogger()->$method(...$args);
    }
}
