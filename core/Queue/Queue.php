<?php

namespace Framework\Core\Queue;

/**
 * Static facade over the configured queue driver.
 *
 * Delivery semantics are defined by {@see QueueDriverInterface}:
 * at-least-once with reserve/ack, retries, and a dead-letter store.
 */
class Queue
{
    protected static $driver = null;

    protected static function init(): void
    {
        if (self::$driver !== null) {
            return;
        }

        $config = function_exists('config') ? config('queue') : self::getDefaultConfig();
        $defaultDriver = $config['default'] ?? 'redis';

        switch ($defaultDriver) {
            case 'redis':
                self::$driver = new Drivers\RedisDriver($config['connections']['redis'] ?? []);
                break;
            case 'database':
                self::$driver = new Drivers\DatabaseDriver($config['connections']['database'] ?? []);
                break;
            default:
                throw new \Exception("Queue driver [{$defaultDriver}] is not supported.");
        }
    }

    protected static function getDefaultConfig(): array
    {
        return [
            'default' => 'redis',
            'connections' => [
                'redis' => [
                    'driver' => 'redis',
                    'host' => '127.0.0.1',
                    'port' => 6379,
                    'database' => 0,
                ],
                'database' => [
                    'driver' => 'database',
                    'table' => '_framework_jobs',
                ],
            ]
        ];
    }

    /**
     * The resolved driver instance. Useful for constructing a Worker or
     * for driver-level operations in commands/tests.
     */
    public static function driver(): QueueDriverInterface
    {
        self::init();
        return self::$driver;
    }

    /**
     * Swap the driver (tests / custom drivers).
     */
    public static function setDriver(?QueueDriverInterface $driver): void
    {
        self::$driver = $driver;
    }

    /**
     * Drop the resolved driver so the next call re-reads config. Called
     * from State::resetPerRequest() in long-running workers.
     */
    public static function reset(): void
    {
        self::$driver = null;
    }

    /**
     * Push a new job onto the queue.
     *
     * @param string|array $callable Example: 'SendEmailJob' or [EmailService::class, 'send']
     * @param array $args Arguments to pass to the job
     * @param string $queue Queue name (default: 'default')
     */
    public static function push($callable, array $args = [], string $queue = 'default'): bool
    {
        return self::driver()->push($queue, $callable, $args);
    }

    /**
     * Push a job that becomes runnable after $delay seconds.
     */
    public static function later(int $delay, $callable, array $args = [], string $queue = 'default'): bool
    {
        return self::driver()->later($delay, $queue, $callable, $args);
    }

    /**
     * Reserve the next job off the queue (invisible to other workers
     * until ack/release or reservation expiry).
     */
    public static function pop(string $queue = 'default'): ?array
    {
        return self::driver()->pop($queue);
    }

    /** Acknowledge successful completion — removes the job for good. */
    public static function ack(array $job): void
    {
        self::driver()->ack($job);
    }

    /** Return a reserved job to the queue after $delay seconds. */
    public static function release(array $job, int $delay = 0): void
    {
        self::driver()->release($job, $delay);
    }

    /** Dead-letter a job with its failure reason. */
    public static function fail(array $job, string $error): void
    {
        self::driver()->fail($job, $error);
    }

    /** List dead-lettered jobs. */
    public static function failedJobs(): array
    {
        return self::driver()->failedJobs();
    }

    /** Re-queue failed job(s); null = all. Returns count. */
    public static function retryFailed($id = null): int
    {
        return self::driver()->retryFailed($id);
    }

    /** Delete failed job(s); null = all. Returns count. */
    public static function flushFailed($id = null): int
    {
        return self::driver()->flushFailed($id);
    }
}
