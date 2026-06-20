<?php

namespace Framework\Core\Queue;

interface QueueDriverInterface
{
    /**
     * Push a new job onto the queue.
     */
    public function push(string $queue, $callable, array $args = []): bool;

    /**
     * Pop the next job off of the queue.
     */
    public function pop(string $queue): ?array;
}
