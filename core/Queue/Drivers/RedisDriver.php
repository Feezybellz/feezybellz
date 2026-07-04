<?php

namespace Framework\Core\Queue\Drivers;

use Framework\Core\Queue\QueueDriverInterface;
use Exception;
use Redis;
use Framework\Core\Support\SystemSetup;

/**
 * Redis-backed queue with at-least-once delivery.
 *
 * Data structures per queue name {q} (all under the configured prefix):
 *   queue:{q}           list      ready jobs (FIFO: lPush / rPop-side lua)
 *   queue:{q}:delayed   zset      score = timestamp the job becomes ready
 *   queue:{q}:reserved  zset      score = reservation expiry; member is the
 *                                 payload as handed to the worker
 *   queue:failed        list      dead-lettered payload envelopes
 *
 * pop() runs a small Lua script so that "migrate due delayed jobs, reclaim
 * expired reservations, take one job, mark it reserved" is a single atomic
 * step — no window where a crashed worker loses a job. The attempt counter
 * is incremented inside the script at reservation time, so crash-reclaimed
 * jobs count attempts correctly.
 */
class RedisDriver implements QueueDriverInterface
{
    protected $redis;

    /** Seconds until a reservation is considered abandoned. */
    protected $retryAfter;

    /**
     * Atomic reserve script.
     * KEYS[1] ready list, KEYS[2] delayed zset, KEYS[3] reserved zset
     * ARGV[1] now, ARGV[2] reservation expiry timestamp
     */
    private const POP_LUA = <<<'LUA'
local due = redis.call('zrangebyscore', KEYS[2], '-inf', ARGV[1])
for i = 1, #due do
    redis.call('zrem', KEYS[2], due[i])
    redis.call('rpush', KEYS[1], due[i])
end
local expired = redis.call('zrangebyscore', KEYS[3], '-inf', ARGV[1])
for i = 1, #expired do
    redis.call('zrem', KEYS[3], expired[i])
    redis.call('rpush', KEYS[1], expired[i])
end
local job = redis.call('lpop', KEYS[1])
if job then
    local decoded = cjson.decode(job)
    decoded['attempts'] = (decoded['attempts'] or 0) + 1
    job = cjson.encode(decoded)
    redis.call('zadd', KEYS[3], ARGV[2], job)
end
return job
LUA;

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

        $this->retryAfter = (int) ($config['retry_after'] ?? 90);
    }

    public function push(string $queue, $callable, array $args = []): bool
    {
        $this->assertSerializableCallable($callable);

        $ok = (bool) $this->redis->lPush(
            "queue:{$queue}",
            $this->encodePayload($queue, $callable, $args, 0)
        );

        $this->notifyWorkers($queue);
        return $ok;
    }

    public function later(int $delay, string $queue, $callable, array $args = []): bool
    {
        $this->assertSerializableCallable($callable);

        if ($delay <= 0) {
            return $this->push($queue, $callable, $args);
        }

        return (bool) $this->redis->zAdd(
            "queue:{$queue}:delayed",
            time() + $delay,
            $this->encodePayload($queue, $callable, $args, 0)
        );
    }

    public function pop(string $queue): ?array
    {
        $now = time();

        $payload = $this->redis->eval(
            self::POP_LUA,
            [
                "queue:{$queue}",
                "queue:{$queue}:delayed",
                "queue:{$queue}:reserved",
                $now,
                $now + $this->retryAfter,
            ],
            3 // first three args are KEYS (phpredis applies OPT_PREFIX to them)
        );

        if (!$payload || !is_string($payload)) {
            return null;
        }

        $decoded = json_decode($payload, true) ?: [];

        return [
            'id'       => $decoded['id'] ?? null,
            'queue'    => $queue,
            'callable' => $decoded['callable'] ?? null,
            'args'     => $decoded['args'] ?? [],
            'attempts' => (int) ($decoded['attempts'] ?? 1),
            // Exact reserved member — ack/release must zRem this string.
            'raw'      => $payload,
        ];
    }

    public function ack(array $job): void
    {
        if (isset($job['raw'])) {
            $this->redis->zRem("queue:{$job['queue']}:reserved", $job['raw']);
        }
    }

    public function release(array $job, int $delay = 0): void
    {
        if (!isset($job['raw'])) {
            return;
        }

        $queue = $job['queue'];
        $this->redis->zRem("queue:{$queue}:reserved", $job['raw']);

        if ($delay > 0) {
            $this->redis->zAdd("queue:{$queue}:delayed", time() + $delay, $job['raw']);
        } else {
            $this->redis->rPush("queue:{$queue}", $job['raw']);
            $this->notifyWorkers($queue);
        }
    }

    public function fail(array $job, string $error): void
    {
        if (isset($job['raw'])) {
            $this->redis->zRem("queue:{$job['queue']}:reserved", $job['raw']);
        }

        $this->redis->lPush('queue:failed', json_encode([
            'id'        => uniqid('failed_', true),
            'queue'     => $job['queue'] ?? 'default',
            'callable'  => $job['callable'] ?? null,
            'args'      => $job['args'] ?? [],
            'error'     => $error,
            'failed_at' => date('Y-m-d H:i:s'),
        ]));
    }

    public function failedJobs(): array
    {
        $entries = $this->redis->lRange('queue:failed', 0, -1) ?: [];

        return array_values(array_filter(array_map(function ($entry) {
            return json_decode($entry, true);
        }, $entries)));
    }

    public function retryFailed($id = null): int
    {
        $count = 0;
        foreach ($this->redis->lRange('queue:failed', 0, -1) ?: [] as $entry) {
            $decoded = json_decode($entry, true);
            if (!is_array($decoded)) {
                continue;
            }
            if ($id !== null && ($decoded['id'] ?? null) !== $id) {
                continue;
            }

            $queue = $decoded['queue'] ?? 'default';
            $this->redis->rPush(
                "queue:{$queue}",
                $this->encodePayload($queue, $decoded['callable'] ?? null, $decoded['args'] ?? [], 0)
            );
            $this->redis->lRem('queue:failed', $entry, 1);
            $count++;
        }

        return $count;
    }

    public function flushFailed($id = null): int
    {
        if ($id === null) {
            $count = (int) $this->redis->lLen('queue:failed');
            $this->redis->del('queue:failed');
            return $count;
        }

        $count = 0;
        foreach ($this->redis->lRange('queue:failed', 0, -1) ?: [] as $entry) {
            $decoded = json_decode($entry, true);
            if (($decoded['id'] ?? null) === $id) {
                $count += (int) $this->redis->lRem('queue:failed', $entry, 1);
            }
        }
        return $count;
    }

    /**
     * Block up to $timeout seconds waiting for a "work available" token —
     * the worker wakes the instant a job is pushed instead of poll-sleeping.
     * Tokens are advisory (pop() may still return null); the token list is
     * capped and expiring so it can't grow unbounded when no worker runs.
     */
    public function awaitJob(string $queue, int $timeout = 1): void
    {
        $this->redis->blPop(["queue:{$queue}:notify"], max(1, $timeout));
    }

    /**
     * Signal any blocked awaitJob() that new work landed.
     */
    protected function notifyWorkers(string $queue): void
    {
        $key = "queue:{$queue}:notify";
        $this->redis->rPush($key, '1');
        $this->redis->lTrim($key, -100, -1); // cap: tokens are advisory
        $this->redis->expire($key, 300);
    }

    protected function encodePayload(string $queue, $callable, array $args, int $attempts): string
    {
        return json_encode([
            // Unique id keeps zset members distinct even for identical jobs.
            'id'        => uniqid('job_', true),
            'callable'  => $callable,
            'args'      => $args,
            'attempts'  => $attempts,
            'pushed_at' => time(),
        ]);
    }

    /**
     * The queue wire format is JSON — refuse closures and object instances
     * (including objects hidden inside array callables).
     */
    protected function assertSerializableCallable($callable): void
    {
        if ($callable instanceof \Closure || is_object($callable)
            || (is_array($callable) && is_object($callable[0] ?? null))) {
            throw new Exception("Cannot push closures or objects to the queue. Please use fully qualified class/method strings.");
        }
    }
}
