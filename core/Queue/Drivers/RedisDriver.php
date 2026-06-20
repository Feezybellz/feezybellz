<?php

namespace Framework\Core\Queue\Drivers;

use Framework\Core\Queue\QueueDriverInterface;
use Exception;
use Redis;
use Framework\Core\Support\SystemSetup;

class RedisDriver implements QueueDriverInterface
{
    protected $redis;

    public function __construct(array $config)
    {
        if (!extension_loaded('redis')) {
            throw new Exception(SystemSetup::getExtensionInstallMessage('redis', 'redis', 6379));
        }

        $this->redis = new Redis();
        
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 6379;
        
        $this->redis->connect($host, $port);

        if (!empty($config['password'])) {
            $this->redis->auth($config['password']);
        }

        if (isset($config['database'])) {
            $this->redis->select($config['database']);
        }
        
        // Add a global prefix to isolate queue keys from cache keys
        $prefix = $config['prefix'] ?? 'framework_queue:';
        $this->redis->setOption(Redis::OPT_PREFIX, $prefix);
    }

    public function push(string $queue, $callable, array $args = []): bool
    {
        if ($callable instanceof \Closure || is_object($callable)) {
            throw new Exception("Cannot push closures or objects to the queue. Please use fully qualified class/method strings.");
        }

        $payload = json_encode([
            'callable' => $callable,
            'args'     => $args,
            'attempts' => 0,
            'pushed_at' => time()
        ]);

        // Push to the left of the list
        return (bool) $this->redis->lPush("queue:{$queue}", $payload);
    }

    public function pop(string $queue): ?array
    {
        // Pop from the right of the list
        $payload = $this->redis->rPop("queue:{$queue}");

        if ($payload) {
            return json_decode($payload, true);
        }

        return null;
    }
}
