<?php

namespace Framework\Core\Queue\Drivers;

use Framework\Core\Queue\QueueDriverInterface;
use Framework\Core\Database\DB;
use Exception;

/**
 * Database-backed queue with at-least-once delivery.
 *
 * Reservation strategy: optimistic locking. pop() claims a candidate row
 * with a conditional UPDATE (`WHERE reserved_at IS NULL OR expired`) and
 * checks the affected-row count — portable across MySQL/Postgres/SQLite,
 * no SKIP LOCKED requirement. Only one competing worker can win the claim.
 *
 * Columns used on the jobs table (see `php console queue:table`):
 *   id, queue, payload, attempts, reserved_at (int), available_at (int),
 *   created_at.
 */
class DatabaseDriver implements QueueDriverInterface
{
    protected $table;
    protected $failedTable;

    /**
     * Seconds after which a reservation is considered abandoned (worker
     * crashed) and the job becomes claimable again.
     */
    protected $retryAfter;

    public function __construct(array $config)
    {
        $this->table = $config['table'] ?? '_framework_jobs';
        $this->failedTable = $config['failed_table'] ?? '_framework_failed_jobs';
        $this->retryAfter = (int) ($config['retry_after'] ?? 90);
    }

    public function push(string $queue, $callable, array $args = []): bool
    {
        return $this->later(0, $queue, $callable, $args);
    }

    public function later(int $delay, string $queue, $callable, array $args = []): bool
    {
        $this->assertSerializableCallable($callable);

        return DB::table($this->table)->insert([
            'queue'        => $queue,
            'payload'      => json_encode(['callable' => $callable, 'args' => $args]),
            'attempts'     => 0,
            'reserved_at'  => null,
            'available_at' => time() + max(0, $delay),
            'created_at'   => date('Y-m-d H:i:s'),
        ]);
    }

    public function pop(string $queue): ?array
    {
        $now = time();
        $staleBefore = $now - $this->retryAfter;

        // Candidate rows: never reserved, or reservation expired (crashed
        // worker), and past their availability time.
        $candidates = DB::table($this->table)
            ->where('queue', '=', $queue)
            ->whereRaw('(reserved_at IS NULL OR reserved_at <= ?)', [$staleBefore])
            ->whereRaw('(available_at IS NULL OR available_at <= ?)', [$now])
            ->orderBy('id', 'asc')
            ->limit(5)
            ->get();

        foreach ($candidates as $row) {
            // Atomic claim: only one worker's UPDATE can match the
            // "unreserved or expired" predicate for this row.
            $claimed = DB::table($this->table)
                ->where('id', '=', $row['id'])
                ->whereRaw('(reserved_at IS NULL OR reserved_at <= ?)', [$staleBefore])
                ->update([
                    'reserved_at' => $now,
                    'attempts'    => ((int) $row['attempts']) + 1,
                ]);

            if ((int) $claimed === 1) {
                $decoded = json_decode($row['payload'], true) ?: [];
                return [
                    'id'       => $row['id'],
                    'queue'    => $queue,
                    'callable' => $decoded['callable'] ?? null,
                    'args'     => $decoded['args'] ?? [],
                    'attempts' => ((int) $row['attempts']) + 1,
                ];
            }
        }

        return null;
    }

    public function ack(array $job): void
    {
        DB::table($this->table)->where('id', '=', $job['id'])->delete();
    }

    public function release(array $job, int $delay = 0): void
    {
        DB::table($this->table)
            ->where('id', '=', $job['id'])
            ->update([
                'reserved_at'  => null,
                'available_at' => time() + max(0, $delay),
            ]);
    }

    public function fail(array $job, string $error): void
    {
        DB::table($this->failedTable)->insert([
            'queue'     => $job['queue'] ?? 'default',
            'payload'   => json_encode([
                'callable' => $job['callable'] ?? null,
                'args'     => $job['args'] ?? [],
            ]),
            'error'     => $error,
            'failed_at' => date('Y-m-d H:i:s'),
        ]);

        DB::table($this->table)->where('id', '=', $job['id'])->delete();
    }

    public function failedJobs(): array
    {
        $rows = DB::table($this->failedTable)->orderBy('id', 'asc')->get();

        return array_map(function ($row) {
            $decoded = json_decode($row['payload'], true) ?: [];
            return [
                'id'        => $row['id'],
                'queue'     => $row['queue'],
                'callable'  => $decoded['callable'] ?? null,
                'args'      => $decoded['args'] ?? [],
                'error'     => $row['error'],
                'failed_at' => $row['failed_at'],
            ];
        }, $rows);
    }

    public function retryFailed($id = null): int
    {
        $query = DB::table($this->failedTable);
        if ($id !== null) {
            $query->where('id', '=', $id);
        }
        $rows = $query->get();

        $count = 0;
        foreach ($rows as $row) {
            DB::table($this->table)->insert([
                'queue'        => $row['queue'],
                'payload'      => $row['payload'],
                'attempts'     => 0,
                'reserved_at'  => null,
                'available_at' => time(),
                'created_at'   => date('Y-m-d H:i:s'),
            ]);
            DB::table($this->failedTable)->where('id', '=', $row['id'])->delete();
            $count++;
        }

        return $count;
    }

    public function flushFailed($id = null): int
    {
        $query = DB::table($this->failedTable);
        if ($id !== null) {
            $query->where('id', '=', $id);
        }
        return (int) $query->delete();
    }

    /**
     * SQL has no blocking-pop primitive, so waiting is a bounded sleep.
     * This also serves as the poll tick that picks up delayed jobs
     * (available_at) and expired reservations.
     */
    public function awaitJob(string $queue, int $timeout = 1): void
    {
        sleep(max(1, min($timeout, 5)));
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
