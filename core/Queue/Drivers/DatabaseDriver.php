<?php

namespace Framework\Core\Queue\Drivers;

use Framework\Core\Queue\QueueDriverInterface;
use Framework\Core\Database\DB;
use Exception;

class DatabaseDriver implements QueueDriverInterface
{
    protected $table;

    public function __construct(array $config)
    {
        $this->table = $config['table'] ?? '_framework_jobs';
    }

    public function push(string $queue, $callable, array $args = []): bool
    {
        if ($callable instanceof \Closure || is_object($callable)) {
            throw new Exception("Cannot push closures or objects to the queue. Please use fully qualified class/method strings.");
        }

        $payload = json_encode([
            'callable' => $callable,
            'args'     => $args
        ]);

        return DB::table($this->table)->insert([
            'queue'      => $queue,
            'payload'    => $payload,
            'attempts'   => 0,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    public function pop(string $queue): ?array
    {
        // Find the oldest job in the queue
        $job = DB::table($this->table)
                 ->where('queue', '=', $queue)
                 ->orderBy('id', 'asc')
                 ->first();

        if ($job) {
            // Delete it immediately to prevent other workers from grabbing it
            // (In a production system, this should use DB locks / transactions like SKIP LOCKED)
            DB::table($this->table)->where('id', '=', $job['id'])->delete();

            $decoded = json_decode($job['payload'], true);
            $decoded['id'] = $job['id'];
            $decoded['attempts'] = $job['attempts'];
            return $decoded;
        }

        return null;
    }
}
