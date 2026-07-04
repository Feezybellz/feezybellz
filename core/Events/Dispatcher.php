<?php

namespace Framework\Core\Events;

class Dispatcher
{
    /**
     * The registered event listeners.
     * @var array
     */
    private static $listeners = [];

    /**
     * Register a listener for a specific event.
     */
    public static function listen(string $eventClass, $listener): void
    {
        self::$listeners[$eventClass][] = $listener;
    }

    /**
     * Register multiple events and listeners at once (useful for config files).
     */
    public static function register(array $events): void
    {
        foreach ($events as $event => $listeners) {
            foreach ($listeners as $listener) {
                self::listen($event, $listener);
            }
        }
    }

    /**
     * Drop every registered listener. Used between requests in long-running
     * workers (see State::resetPerRequest) and between tests.
     */
    public static function flush(): void
    {
        self::$listeners = [];
    }

    /**
     * Dispatch an event and trigger all its listeners.
     */
    public static function dispatch(object $event): void
    {
        $eventClass = get_class($event);

        // If no one is listening to this event, just do nothing
        if (!isset(self::$listeners[$eventClass])) {
            return;
        }

        foreach (self::$listeners[$eventClass] as $listener) {
            if (is_callable($listener)) {
                // If it's a simple closure/function
                call_user_func($listener, $event);
            } elseif (is_string($listener) && class_exists($listener)) {

                // Use the Dependency Injection Container so Event Listeners
                // can auto-inject Services into their constructors.
                if (class_exists('\Framework\Core\Container\Container')) {
                    $instance = \Framework\Core\Container\Container::getInstance()->make($listener);
                } else {
                    $instance = new $listener();
                }

                // Queue integration: listeners implementing ShouldQueue (or
                // the legacy duck-typed shouldQueue() method returning true)
                // run in the background instead of slowing the HTTP request.
                //
                // Wire format note: the queue drivers JSON-encode payloads,
                // so we push CLASS STRINGS, never object instances — the
                // worker rebuilds the event from its public properties and
                // resolves the listener through the Container. (Pushing
                // [$instance, 'handle'] here used to produce a payload the
                // worker could not reconstruct.)
                if (self::listenerWantsQueue($instance)) {
                    if (class_exists('\Framework\Core\Queue\Queue')) {
                        \Framework\Core\Queue\Queue::push(
                            [self::class, 'handleQueuedListener'],
                            [$listener, $eventClass, get_object_vars($event)]
                        );
                        continue;
                    }
                }

                // Run it synchronously
                if (method_exists($instance, 'handle')) {
                    $instance->handle($event);
                }
            }
        }
    }

    /**
     * Worker-side entry point for a queued listener. Rebuilds the event
     * object from its public properties, resolves the listener through the
     * Container, and runs it.
     *
     * Invoked by the queue worker as [Dispatcher::class, 'handleQueuedListener'].
     */
    public static function handleQueuedListener(string $listenerClass, string $eventClass, array $properties): void
    {
        if (!class_exists($listenerClass)) {
            throw new \RuntimeException("Queued listener class not found: {$listenerClass}");
        }
        if (!class_exists($eventClass)) {
            throw new \RuntimeException("Queued event class not found: {$eventClass}");
        }

        $event = self::rehydrateEvent($eventClass, $properties);

        if (class_exists('\Framework\Core\Container\Container')) {
            $instance = \Framework\Core\Container\Container::getInstance()->make($listenerClass);
        } else {
            $instance = new $listenerClass();
        }

        if (!method_exists($instance, 'handle')) {
            throw new \RuntimeException("Queued listener {$listenerClass} has no handle() method.");
        }

        $instance->handle($event);
    }

    /**
     * Rebuild an event object from its JSON-round-tripped public properties.
     * The constructor is bypassed on purpose: its arguments are unknown here,
     * and the public-property snapshot is the source of truth.
     */
    private static function rehydrateEvent(string $eventClass, array $properties): object
    {
        $ref = new \ReflectionClass($eventClass);
        $event = $ref->newInstanceWithoutConstructor();

        foreach ($properties as $name => $value) {
            if ($ref->hasProperty($name) && $ref->getProperty($name)->isPublic()) {
                $event->{$name} = $value;
            }
        }

        return $event;
    }

    /**
     * Should this listener instance run on the queue?
     */
    private static function listenerWantsQueue(object $instance): bool
    {
        if ($instance instanceof ShouldQueue) {
            return true;
        }
        // Legacy duck-typing, kept for BC and for conditional queueing.
        return method_exists($instance, 'shouldQueue') && $instance->shouldQueue();
    }
}
