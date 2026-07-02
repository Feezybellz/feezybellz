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
     * Ambient context added to every log line. Populated by Kernel::handle()
     * with a request_id and (optionally) user_id, so all logs emitted during
     * one request can be grepped together across processes.
     * @var array<string, mixed>
     */
    protected static array $context = [];

    /**
     * Replace the ambient context. Typically called once per request from
     * Kernel; call again on user login to add user_id, etc.
     */
    public static function setContext(array $context): void
    {
        self::$context = $context;
    }

    /** Merge additional keys into the ambient context. */
    public static function withContext(array $extra): void
    {
        self::$context = array_replace(self::$context, $extra);
    }

    public static function getContext(): array
    {
        return self::$context;
    }

    public static function clearContext(): void
    {
        self::$context = [];
    }

    /**
     * Get the singleton Logger instance. Reads config('logging.*') so tests
     * can swap level/channel via config([...]) without touching this class.
     */
    public static function getLogger(): Logger
    {
        if (self::$logger === null) {
            $level = function_exists('config') ? (config('logging.level') ?? 'info') : 'info';
            self::$logger = new Logger(null, $level);
        }
        return self::$logger;
    }

    /**
     * Swap the underlying logger. Primarily for tests — inject a fake or
     * an in-memory logger to make assertions on what got logged.
     */
    public static function setLogger(?Logger $logger): void
    {
        self::$logger = $logger;
    }

    /**
     * Drop the cached logger so the next call rebuilds from config.
     * Called by Framework\Core\State::resetPerRequest() indirectly if wired.
     */
    public static function reset(): void
    {
        self::$logger = null;
    }

    /**
     * Pass any static calls dynamically to the underlying Logger instance.
     * If the caller passed a $context array as the last arg, we merge our
     * ambient context (request_id etc.) into it so every log line carries
     * the trace keys automatically.
     */
    public static function __callStatic($method, $args)
    {
        if (!empty(self::$context)) {
            // Log methods are (string $message, array $context = []).
            $context = $args[1] ?? [];
            if (is_array($context)) {
                $args[1] = array_replace(self::$context, $context);
            }
        }
        return self::getLogger()->$method(...$args);
    }
}
