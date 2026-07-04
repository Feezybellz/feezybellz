# Queue

Background job processing with **at-least-once delivery**: jobs are
reserved (not deleted) when a worker picks them up, retried with
exponential backoff on failure, and dead-lettered after exhausting
their attempts.

Two distinct systems live under this name — pick the right one:

| System | Entry point | Use when |
|---|---|---|
| **Driver queue** (this doc) | `Queue::push()` + `php console queue:work` | Standard background jobs via Redis or database |
| **QueueServer** (in-memory daemon) | `QueueClient::dispatch()` + `php console queue:serve` | Ultra-low-latency local dispatch; see `config/queue_server.php` |

## Configuration

`config/queue.php`:

```php
'default' => env('QUEUE_DRIVER', 'redis'),   // 'redis' or 'database'

'connections' => [
    'redis' => [
        'host'        => env('REDIS_HOST', '127.0.0.1'),
        'port'        => (int) env('REDIS_PORT', 6379),
        'retry_after' => 90,   // seconds before a crashed worker's job is reclaimed
    ],
    'database' => [
        'table'        => '_framework_jobs',
        'failed_table' => '_framework_failed_jobs',
        'retry_after'  => 90,
    ],
],
```

`retry_after` must exceed your longest-running job, or a slow job will
be handed to a second worker while the first is still on it.

For the database driver, create the tables once:

```bash
php console queue:table   # writes the migration (jobs + failed_jobs)
php console migrate
```

## Pushing jobs — the wire format rule

Payloads are JSON, so callables must be **strings or class-name
arrays** — never closures or object instances (drivers reject them):

```php
use Framework\Core\Queue\Queue;

// ── Style 1: [class, method] — instance method (fresh instance per run)
Queue::push([\App\Jobs\SendWelcomeEmail::class, 'handle'], [$userId]);

// ── Style 2: [class, staticMethod] — no instantiation
Queue::push([\App\Reports\Builder::class, 'rebuildAll']);

// ── Style 3: plain function name
Queue::push('my_maintenance_function', [$arg1, $arg2]);

// ── Named queue (third argument)
Queue::push([\App\Jobs\Heavy::class, 'handle'], [], 'slow');

// ── Delayed: runnable only after N seconds
Queue::later(300, [\App\Jobs\FollowUp::class, 'handle'], [$orderId]);
```

## Running the worker

```bash
php console queue:work                 # work the "default" queue, forever
php console queue:work emails          # a named queue
php console queue:work --once          # one job (or one empty poll), then exit
php console queue:work --tries=5       # attempts before dead-lettering (default 3)
php console queue:work --backoff=10    # base retry delay in seconds (default 5)
php console queue:work --sleep=5       # max idle wait per cycle (default 1)
```

- **Retries**: on failure the job is released back with exponential
  backoff — `backoff × 2^(attempt−1)`, capped at 1 hour. After `--tries`
  attempts it moves to the failed-jobs store with the exception.
- **Idle waiting**: the Redis driver blocks on a notify token and wakes
  the *instant* work is pushed; the database driver polls with a bounded
  sleep. `--sleep` is an upper bound on idle latency, not a fixed pause.
- **Crash safety**: if a worker dies mid-job, the reservation expires
  after `retry_after` and another worker picks the job up (attempt
  counter intact).

Run it under a supervisor in production (systemd example):

```ini
[Service]
ExecStart=/usr/bin/php /var/www/app/console queue:work --tries=3
Restart=always
```

## Failed jobs (dead-letter queue)

```bash
php console queue:failed          # list failed jobs with errors
php console queue:retry <id>      # push one back onto its queue
php console queue:retry --all     # retry everything
php console queue:flush [id]      # delete failed job(s) permanently
```

Programmatic access:

```php
Queue::failedJobs();       // array of {id, queue, callable, args, error, failed_at}
Queue::retryFailed($id);   // null = all; returns count re-queued
Queue::flushFailed($id);   // null = all; returns count deleted
```

## Manual consumption (advanced)

If you build your own worker loop, honor the reserve/ack contract:

```php
$job = Queue::pop('default');      // reserves — invisible to other workers
if ($job !== null) {
    try {
        run($job['callable'], $job['args']);
        Queue::ack($job);          // success: delete for good
    } catch (\Throwable $e) {
        if ($job['attempts'] < 3) {
            Queue::release($job, 30);          // retry in 30s
        } else {
            Queue::fail($job, $e->getMessage()); // dead-letter
        }
    }
}
```

Or reuse the framework's `Worker`, which implements exactly that loop:

```php
use Framework\Core\Queue\{Queue, Worker};

$worker = new Worker(Queue::driver(), tries: 3, backoff: 5);
$worker->runNextJob('default');   // true = ran ok, false = failed, null = empty
```

## Queued event listeners

Event listeners implementing `ShouldQueue` are pushed to this queue
automatically — see [events/](../events/README.md).

## Testing queue code

Use the in-memory driver so tests need no Redis/DB:

```php
use Tests\Fixtures\ArrayQueueDriver;
use Framework\Core\Queue\{Queue, Worker};

$driver = new ArrayQueueDriver();
Queue::setDriver($driver);              // inject; Queue::reset() to undo

$driver->push('default', [MyJob::class, 'handle'], [42]);
(new Worker($driver, 1, 0))->runNextJob('default');

$this->assertEmpty($driver->failed);
```
