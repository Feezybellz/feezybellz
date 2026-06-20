<?php

namespace Framework\Core\Queue;

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
     * Push a new job onto the queue.
     * 
     * @param string|array $callable Example: 'SendEmailJob' or [EmailService::class, 'send']
     * @param array $args Arguments to pass to the job
     * @param string $queue Queue name (default: 'default')
     */
    public static function push($callable, array $args = [], string $queue = 'default'): bool
    {
        self::init();
        return self::$driver->push($queue, $callable, $args);
    }

    /**
     * Pop the next job off the queue.
     * 
     * @param string $queue Queue name (default: 'default')
     */
    public static function pop(string $queue = 'default'): ?array
    {
        self::init();
        return self::$driver->pop($queue);
    }
}
