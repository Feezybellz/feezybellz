<?php

namespace Framework\Core\Queue;

/**
 * Executes queue jobs with at-least-once semantics.
 *
 * Lifecycle per job:
 *   pop (reserve) → run callable →
 *     success        → ack (delete)
 *     failure, tries left  → release with exponential backoff
 *     failure, exhausted   → fail (dead-letter with the exception)
 *
 * Deliberately decoupled from the console so it can be driven by tests,
 * daemons, or custom supervisors. QueueWorkCommand is a thin wrapper.
 */
class Worker
{
    private QueueDriverInterface $driver;

    /** Max times a job is handed to a worker before dead-lettering. */
    private int $tries;

    /** Base seconds for exponential backoff: base * 2^(attempts-1). */
    private int $backoff;

    /** Hard cap so exponential backoff can't grow unbounded. */
    private const MAX_BACKOFF = 3600;

    /** @var callable|null fn(string $level, string $message) */
    private $logger;

    public function __construct(
        QueueDriverInterface $driver,
        int $tries = 3,
        int $backoff = 5,
        ?callable $logger = null
    ) {
        $this->driver = $driver;
        $this->tries = max(1, $tries);
        $this->backoff = max(0, $backoff);
        $this->logger = $logger;
    }

    /**
     * Reserve and run the next job.
     *
     * @return bool|null true = job succeeded, false = job failed,
     *                   null = queue was empty.
     */
    public function runNextJob(string $queue = 'default'): ?bool
    {
        $job = $this->driver->pop($queue);
        if ($job === null) {
            return null;
        }

        return $this->process($job);
    }

    /**
     * Run one reserved job and settle it (ack / release / fail).
     */
    public function process(array $job): bool
    {
        $name = $this->jobName($job);
        $attempts = (int) ($job['attempts'] ?? 1);

        $this->log('info', "Processing: {$name} (attempt {$attempts}/{$this->tries})");

        try {
            $this->execute($job);

            $this->driver->ack($job);
            $this->log('success', "Processed: {$name}");
            return true;
        } catch (\Throwable $e) {
            if ($attempts < $this->tries) {
                $delay = $this->backoffDelay($attempts);
                $this->driver->release($job, $delay);
                $this->log('error', "Failed: {$name} — {$e->getMessage()} (retry in {$delay}s)");
            } else {
                $this->driver->fail($job, $this->describeFailure($e));
                $this->log('error', "Failed permanently: {$name} — {$e->getMessage()} (moved to failed jobs)");
            }
            return false;
        }
    }

    /**
     * Invoke the job's callable. Wire format: FQCN strings only.
     */
    private function execute(array $job): void
    {
        $callable = $job['callable'] ?? null;
        $args = $job['args'] ?? [];

        if (is_array($callable)) {
            [$class, $method] = $callable + [null, null];
            if (!is_string($class) || !class_exists($class)) {
                throw new \RuntimeException("Job class not found: " . json_encode($class));
            }
            // Static methods don't need an instance; instance methods get
            // a fresh one.
            $target = (new \ReflectionMethod($class, $method))->isStatic()
                ? $class
                : new $class();
            call_user_func_array([$target, $method], $args);
            return;
        }

        if (is_string($callable) && is_callable($callable)) {
            call_user_func_array($callable, $args);
            return;
        }

        throw new \RuntimeException("Job payload has no runnable callable.");
    }

    /**
     * Exponential backoff, capped: base * 2^(attempts-1).
     */
    private function backoffDelay(int $attempts): int
    {
        if ($this->backoff === 0) {
            return 0;
        }
        return (int) min(self::MAX_BACKOFF, $this->backoff * (2 ** max(0, $attempts - 1)));
    }

    private function jobName(array $job): string
    {
        $callable = $job['callable'] ?? null;
        return is_array($callable) ? implode('::', $callable) : (string) $callable;
    }

    private function describeFailure(\Throwable $e): string
    {
        return get_class($e) . ': ' . $e->getMessage()
            . ' at ' . $e->getFile() . ':' . $e->getLine();
    }

    private function log(string $level, string $message): void
    {
        if ($this->logger !== null) {
            ($this->logger)($level, "[" . date('Y-m-d H:i:s') . "] {$message}");
        }
    }
}
