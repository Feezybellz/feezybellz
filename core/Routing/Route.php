<?php

namespace Framework\Core\Routing;

class Route
{
    public $method;
    public $path;
    public $handler;
    public $subdomain = null;
    public $middleware = [];
    public $name = null;
    
    public function __construct(string $method, string $path, $handler)
    {
        $this->method = $method;
        $this->path = $path;
        $this->handler = $handler;
    }
    
    /**
     * Set subdomain constraint
     * 
     * @param string $subdomain Subdomain pattern (e.g., 'api', 'admin', '{account}')
     * @return self
     */
    public function subdomain(string $subdomain): self
    {
        $this->subdomain = $subdomain;
        return $this;
    }
    
    /**
     * Add middleware to route
     * 
     * @param string|object $middleware Middleware class name or instance
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
    
    /**
     * Set route name
     * 
     * @param string $name Route name for URL generation
     * @return self
     */
    public function name(string $name): self
    {
        $this->name = $name;
        return $this;
    }
}
