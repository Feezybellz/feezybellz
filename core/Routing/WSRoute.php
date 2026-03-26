<?php

namespace Framework\Core\Routing;

/**
 * WSRoute represents a registered WebSocket event handler.
 * It allows fluent chaining of middleware and other properties.
 */
class WSRoute
{
    public $event;
    public $handler;
    public $middleware = [];

    public function __construct(string $event, $handler)
    {
        $this->event = $event;
        $this->handler = $handler;
    }

    /**
     * Add middleware to this specific event.
     * 
     * @param string|array $middleware
     * @return self
     */
    public function middleware($middleware): self
    {
        if (is_array($middleware)) {
            $this->middleware = array_merge($this->middleware, $middleware);
        } else {
            $this->middleware[] = $middleware;
        }
        return $this;
    }
}
