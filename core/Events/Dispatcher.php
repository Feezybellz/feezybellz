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
                // If it's a dedicated Listener class, instantiate and call its handle() method
                $instance = new $listener();
                
                // (Bonus!) If you later want to integrate with your existing Queue system:
                // if (method_exists($instance, 'shouldQueue') && $instance->shouldQueue()) {
                //     Queue::push($listener, 'handle', [$event]);
                //     continue;
                // }

                if (method_exists($instance, 'handle')) {
                    $instance->handle($event);
                }
            }
        }
    }
}
