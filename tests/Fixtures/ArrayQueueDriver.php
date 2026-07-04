<?php

namespace Tests\Fixtures;

use Framework\Core\Queue\QueueDriverInterface;

/**
 * In-memory queue driver implementing the full at-least-once contract.
 * Lets Worker/lifecycle tests run without Redis or a database, and exposes
 * its internals so assertions can inspect exactly what happened.
 */
class ArrayQueueDriver implements QueueDriverInterface
{
    /** @var array<string, array<int, array>> ready jobs per queue */
    public array $ready = [];

    /** @var array<int, array> reserved jobs keyed by id */
    public array $reserved = [];

    /** @var array<int, array> dead-lettered jobs */
    public array $failed = [];

    /** @var array<int, array{job: array, delay: int}> release log */
    public array $releases = [];

    private int $nextId = 1;

    public function push(string $queue, $callable, array $args = []): bool
    {
        return $this->later(0, $queue, $callable, $args);
    }

    public function later(int $delay, string $queue, $callable, array $args = []): bool
    {
        $this->ready[$queue][] = [
            'id'           => $this->nextId++,
            'queue'        => $queue,
            'callable'     => $callable,
            'args'         => $args,
            'attempts'     => 0,
            'available_at' => time() + $delay,
        ];
        return true;
    }

    public function pop(string $queue): ?array
    {
        foreach ($this->ready[$queue] ?? [] as $index => $job) {
            if ($job['available_at'] <= time()) {
                array_splice($this->ready[$queue], $index, 1);
                $job['attempts']++;
                $this->reserved[$job['id']] = $job;
                return $job;
            }
        }
        return null;
    }

    public function ack(array $job): void
    {
        unset($this->reserved[$job['id']]);
    }

    public function release(array $job, int $delay = 0): void
    {
        unset($this->reserved[$job['id']]);
        $job['available_at'] = time() + $delay;
        $this->ready[$job['queue']][] = $job;
        $this->releases[] = ['job' => $job, 'delay' => $delay];
    }

    public function fail(array $job, string $error): void
    {
        unset($this->reserved[$job['id']]);
        $this->failed[] = [
            'id'        => $job['id'],
            'queue'     => $job['queue'],
            'callable'  => $job['callable'],
            'args'      => $job['args'],
            'error'     => $error,
            'failed_at' => date('Y-m-d H:i:s'),
        ];
    }

    public function failedJobs(): array
    {
        return $this->failed;
    }

    public function retryFailed($id = null): int
    {
        $count = 0;
        foreach ($this->failed as $index => $job) {
            if ($id !== null && $job['id'] !== $id) {
                continue;
            }
            unset($this->failed[$index]);
            $this->push($job['queue'], $job['callable'], $job['args']);
            $count++;
        }
        $this->failed = array_values($this->failed);
        return $count;
    }

    public function flushFailed($id = null): int
    {
        if ($id === null) {
            $count = count($this->failed);
            $this->failed = [];
            return $count;
        }

        $before = count($this->failed);
        $this->failed = array_values(array_filter(
            $this->failed,
            fn ($job) => $job['id'] !== $id
        ));
        return $before - count($this->failed);
    }

    public function awaitJob(string $queue, int $timeout = 1): void
    {
        // In-memory driver: nothing to block on; tests never need to wait.
    }

    /** Total jobs sitting ready across all queues. */
    public function readyCount(): int
    {
        return array_sum(array_map('count', $this->ready));
    }
}
