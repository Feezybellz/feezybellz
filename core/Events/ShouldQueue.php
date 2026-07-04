<?php

namespace Framework\Core\Events;

/**
 * Marker interface: a listener implementing this is pushed to the queue
 * instead of running synchronously in the dispatching request.
 *
 * Requirements for queued listeners:
 *  - The event's public properties must be JSON-safe (scalars/arrays);
 *    the event object is rebuilt from them on the worker side.
 *  - The listener is resolved through the Container on the worker, so
 *    constructor dependency injection works as usual.
 *
 * The legacy duck-typed `shouldQueue(): bool` instance method is still
 * honored for conditional queueing.
 */
interface ShouldQueue
{
}
