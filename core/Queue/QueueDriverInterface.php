<?php

namespace Framework\Core\Queue;

/**
 * Contract for queue drivers.
 *
 * Delivery model: AT-LEAST-ONCE.
 *
 *  - pop() RESERVES a job (it becomes invisible to other workers) instead
 *    of deleting it. A job is only gone once the worker calls ack().
 *  - If a worker crashes mid-job, the reservation expires after the
 *    configured `retry_after` seconds and the job becomes poppable again.
 *  - On handler failure the worker calls release() (retry with delay) or,
 *    once attempts are exhausted, fail() — which moves the payload to the
 *    dead-letter store inspectable via failedJobs()/retryFailed().
 *
 * Job array shape (returned by pop, accepted by ack/release/fail):
 *
 *   [
 *     'id'       => int|string|null,  // driver-specific handle
 *     'queue'    => string,
 *     'callable' => string|array,     // FQCN strings only, never objects
 *     'args'     => array,
 *     'attempts' => int,              // times handed to a worker (>= 1)
 *     'raw'      => string,           // driver-internal (Redis)
 *   ]
 */
interface QueueDriverInterface
{
    /**
     * Push a job onto the queue, runnable immediately.
     */
    public function push(string $queue, $callable, array $args = []): bool;

    /**
     * Push a job that becomes runnable only after $delay seconds.
     */
    public function later(int $delay, string $queue, $callable, array $args = []): bool;

    /**
     * Reserve the next available job. Returns null when the queue is empty.
     * The job stays invisible to other workers until ack()/release() or
     * until its reservation expires (`retry_after`).
     */
    public function pop(string $queue): ?array;

    /**
     * Acknowledge successful completion — permanently removes the job.
     */
    public function ack(array $job): void;

    /**
     * Return a reserved job to the queue, runnable after $delay seconds.
     * The attempt counter has already been incremented by pop().
     */
    public function release(array $job, int $delay = 0): void;

    /**
     * Move a reserved job to the dead-letter store with the failure reason.
     */
    public function fail(array $job, string $error): void;

    /**
     * List dead-lettered jobs. Each entry: id, queue, callable, args,
     * error, failed_at.
     */
    public function failedJobs(): array;

    /**
     * Move failed job(s) back onto their queue with a reset attempt
     * counter. Pass an id for one job, null for all.
     *
     * @return int Number of jobs re-queued.
     */
    public function retryFailed($id = null): int;

    /**
     * Delete failed job(s). Pass an id for one job, null for all.
     *
     * @return int Number of jobs deleted.
     */
    public function flushFailed($id = null): int;

    /**
     * Block until a job MAY be available on the queue, or until $timeout
     * seconds pass — whichever comes first. Called by the worker when
     * pop() returned null, instead of a fixed sleep.
     *
     * Drivers with a native blocking primitive (Redis BLPOP) wake the
     * instant work arrives; drivers without one may simply sleep briefly.
     * A wakeup is advisory: the caller must still pop() and may get null.
     */
    public function awaitJob(string $queue, int $timeout = 1): void;
}
