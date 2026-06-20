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
                
                // OPTIMIZATION 1: Use the Dependency Injection Container!
                // This allows Event Listeners to auto-inject Services into their constructors.
                if (class_exists('\Framework\Core\Container\Container')) {
                    $instance = \Framework\Core\Container\Container::getInstance()->make($listener);
                } else {
                    $instance = new $listener();
                }
                
                // OPTIMIZATION 2: Queue Integration!
                // If the Listener implements 'ShouldQueue' or has a 'shouldQueue' method,
                // we push it to the background so it doesn't slow down the HTTP request.
                if (method_exists($instance, 'shouldQueue') && $instance->shouldQueue()) {
                    if (class_exists('\Framework\Core\Queue\Queue')) {
                        \Framework\Core\Queue\Queue::push([$instance, 'handle'], [$event]);
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
}
