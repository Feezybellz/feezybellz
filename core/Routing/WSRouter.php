<?php

namespace Framework\Core\Routing;

use Framework\Core\WebSocket\ClientSocket;

/**
 * WSRouter — expressive static router for WebSocket events.
 * It follows the same patterns as the HTTP Router.
 */
class WSRouter
{
    /** @var WSRoute[] */
    protected static $routes = [];
    protected static $groupStack = [];

    /**
     * Register a WebSocket event with a handler.
     * Returns a WSRoute object for further chaining.
     * 
     * @param string $event
     * @param array|callable $handler
     * @return WSRoute
     */
    public static function on(string $event, $handler): WSRoute
    {
        $route = new WSRoute($event, $handler);
        $namespace = '';

        // Apply group attributes from the stack (Outer to Inner)
        if (!empty(self::$groupStack)) {
            foreach (self::$groupStack as $group) {
                // 1. Gather Middleware
                if (isset($group['middleware'])) {
                    $route->middleware($group['middleware']);
                }

                // 2. Build Cumulative Namespace
                if (isset($group['namespace'])) {
                    $namespace .= trim($group['namespace'], '\\') . '\\';
                }
            }
        }

        // Apply final namespace if it's a Controller handler
        if ($namespace !== '' && is_array($handler) && is_string($handler[0])) {
            $handler[0] = $namespace . ltrim($handler[0], '\\');
            $route->handler = $handler;
        }

        self::$routes[] = $route;
        return $route;
    }

    /**
     * Create an event group with custom attributes.
     */
    public static function group(array $attributes, callable $callback): void
    {
        self::$groupStack[] = $attributes;
        $callback();
        array_pop(self::$groupStack);
    }

    /**
     * Apply middleware to a group of events.
     */
    public static function middleware($middleware, callable $callback): void
    {
        self::group(['middleware' => $middleware], $callback);
    }

    /**
     * Attach global server-side events (like 'connection' or 'disconnect')
     */
    public static function attachServer($server): void
    {
        $globalEvents = ['connection', 'disconnect'];

        foreach (self::$routes as $route) {
            if (in_array($route->event, $globalEvents)) {
                $handler = $route->handler;
                $middleware = $route->middleware;

                $server->on($route->event, function ($data, $socket, $reply) use ($handler, $middleware) {
                    self::runMiddlewarePipeline($middleware, $data, $socket, $reply, function($finalData, $finalSocket, $finalReply) use ($handler) {
                        self::execute($handler, $finalData, $finalSocket, $finalReply);
                    });
                });
            }
        }
    }

    /**
     * Attach all registered per-client events to a new connection.
     */
    public static function attach(ClientSocket $socket): void
    {
        $globalEvents = ['connection', 'disconnect'];

        foreach (self::$routes as $route) {
            $event = $route->event;

            if (in_array($event, $globalEvents)) {
                continue; // Global events are handled via attachServer()
            }

            $handler = $route->handler;
            $middleware = $route->middleware;

            $socket->on($event, function ($passData, $socket, $reply) use ($handler, $middleware) {
                $isWrapped = is_array($passData) && (isset($passData['data']) || isset($passData['clientId']));
                $actualData = $isWrapped ? ($passData['data'] ?? []) : ($passData ?? []);
                $ackId = $isWrapped ? ($passData['_ackId'] ?? null) : ($actualData['_ackId'] ?? null);

                if ($ackId && is_array($actualData)) {
                    $actualData['_ackId'] = $ackId;
                }

                self::runMiddlewarePipeline($middleware, $actualData, $socket, $reply, function($finalData, $finalSocket, $finalReply) use ($handler) {
                    self::execute($handler, $finalData, $finalSocket, $finalReply);
                });
            });
        }
    }

    /**
     * Pipeline execution.
     */
    protected static function runMiddlewarePipeline(array $middleware, $data, $socket, $reply, callable $destination)
    {
        $pipeline = array_reduce(
            array_reverse($middleware),
            function ($next, $middlewareClass) {
                return function ($passData, $passSocket, $passReply) use ($next, $middlewareClass) {
                    $instance = new $middlewareClass();
                    if (method_exists($instance, 'handleWS')) {
                        return $instance->handleWS($passData, $passSocket, $next, $passReply);
                    }
                    throw new \Exception("Middleware {$middlewareClass} missing handleWS method.");
                };
            },
            $destination
        );

        return $pipeline($data, $socket, $reply);
    }

    /**
     * Action execution.
     */
    protected static function execute($handler, $data, $socket, $reply): void
    {
        if (is_array($handler)) {
            [$class, $method] = $handler;
            $instance = new $class();
            $instance->{$method}($data, $socket, $reply);
        } else {
            $handler($data, $socket, $reply);
        }
    }
}
