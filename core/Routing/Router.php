<?php

namespace Framework\Core\Routing;

use Framework\Core\Http\Request;
use Framework\Core\Http\Response;
use Framework\Core\Container\Container;

class Router
{
    protected static $routes = [];
    protected static $groupStack = [];
    protected static $request = null;
    protected static $response = null;
    protected static $container = null;
    protected static $errorHandler = null;

    /**
     * Set a custom 404 error handler
     */
    public static function set404Handler(callable $callback): void
    {
        self::$errorHandler = $callback;
    }
    
    /**
     * Set the dependency injection container.
     */
    public static function setContainer(Container $container): void
    {
        self::$container = $container;
    }

    /**
     * Initialize the router with request and response
     * * @param Request $request
     * @param Response $response
     * @return void
     */
    public static function init(Request $request, Response $response): void
    {
        self::$request = $request;
        self::$response = $response;
        self::registerInternalRoutes();
    }
    
    /**
     * Register a GET route
     * * @param string $path
     * @param mixed ...$args Middleware and handler (last argument is handler)
     * @return Route
     */
    public static function get(string $path, ...$args): Route
    {
        return self::addRouteWithMiddleware('GET', $path, $args);
    }
    
    /**
     * Register a POST route
     * * @param string $path
     * @param mixed ...$args Middleware and handler (last argument is handler)
     * @return Route
     */
    public static function post(string $path, ...$args): Route
    {
        return self::addRouteWithMiddleware('POST', $path, $args);
    }
    
    /**
     * Register a PUT route
     * * @param string $path
     * @param mixed ...$args Middleware and handler (last argument is handler)
     * @return Route
     */
    public static function put(string $path, ...$args): Route
    {
        return self::addRouteWithMiddleware('PUT', $path, $args);
    }
    
    /**
     * Register a DELETE route
     * * @param string $path
     * @param mixed ...$args Middleware and handler (last argument is handler)
     * @return Route
     */
    public static function delete(string $path, ...$args): Route
    {
        return self::addRouteWithMiddleware('DELETE', $path, $args);
    }
    
    /**
     * Register a PATCH route
     * * @param string $path
     * @param mixed ...$args Middleware and handler (last argument is handler)
     * @return Route
     */
    public static function patch(string $path, ...$args): Route
    {
        return self::addRouteWithMiddleware('PATCH', $path, $args);
    }
    
    /**
     * Register an OPTIONS route
     * * @param string $path
     * @param mixed ...$args Middleware and handler (last argument is handler)
     * @return Route
     */
    public static function options(string $path, ...$args): Route
    {
        return self::addRouteWithMiddleware('OPTIONS', $path, $args);
    }

    /**
     * Register a route that responds to ANY HTTP method
     * * @param string $path
     * @param mixed ...$args Middleware and handler (last argument is handler)
     * @return Route
     */
    public static function any(string $path, ...$args): Route
    {
        // We register the method as 'ANY' (a custom wildcard string)
        return self::addRouteWithMiddleware('ANY', $path, $args);
    }
    
    /**
     * Create a route group with prefix and optional middleware
     * Usage: Router::prefix('/api', $middleware1, $middleware2, function() { ... })
     * * @param string $prefix
     * @param mixed ...$args Middleware and callback
     * @return void
     */
    public static function prefix(string $prefix, ...$args): void
    {
        $callback = array_pop($args);
        $middleware = $args;
        
        self::$groupStack[] = [
            'prefix' => $prefix,
            'middleware' => $middleware,
        ];
        
        $callback();
        
        array_pop(self::$groupStack);
    }
    
    /**
     * Create a route group with subdomain and optional middleware
     * Usage: Router::subdomain('api', $middleware1, function() { ... })
     * * @param string $subdomain
     * @param mixed ...$args Middleware and callback
     * @return void
     */
    public static function subdomain(string $subdomain, ...$args): void
    {
        $callback = array_pop($args);
        $middleware = $args;
        
        self::$groupStack[] = [
            'subdomain' => $subdomain,
            'middleware' => $middleware,
        ];
        
        $callback();
        
        array_pop(self::$groupStack);
    }

    /**
     * Create a route group for a specific API version
     * * Usage 1: Router::version('v1', $middleware, function() { ... })
     * Usage 2: Router::version('v1', ['namespace' => 'App\Controllers\Api\V1'], function() { ... })
     * * @param string $version The version string (e.g., 'v1')
     * @param mixed ...$args Attributes/Middleware and callback
     * @return void
     */
    public static function version(string $version, ...$args): void
    {
        $callback = array_pop($args);
        
        // Ensure the version string is properly prefixed with a slash
        $attributes = [
            'prefix' => '/' . ltrim($version, '/')
        ];

        // Check if the next argument is an associative array of attributes (like namespace)
        if (!empty($args) && is_array($args[0]) && (array_keys($args[0]) !== range(0, count($args[0]) - 1))) {
            $attributes = array_merge($attributes, array_shift($args));
        } else {
            // Otherwise, treat any remaining arguments as middleware
            $attributes['middleware'] = $args;
        }
        
        self::group($attributes, $callback);
    }

    /**
     * Create a route group for a specific API version using a URL Prefix.
     * Generates routes like: /v1/users
     * * @param string $version The version string (e.g., 'v1')
     * @param mixed ...$args Attributes/Middleware and callback
     * @return void
     */
    public static function prefixVersion(string $version, ...$args): void
    {
        $callback = array_pop($args);
        
        // Ensure the version string is properly prefixed with a slash
        $attributes = [
            'prefix' => '/' . ltrim($version, '/')
        ];

        // Check if the next argument is an associative array of attributes (like namespace)
        if (!empty($args) && is_array($args[0]) && (array_keys($args[0]) !== range(0, count($args[0]) - 1))) {
            $attributes = array_merge($attributes, array_shift($args));
        } else {
            // Otherwise, treat any remaining arguments as middleware
            $attributes['middleware'] = $args;
        }
        
        self::group($attributes, $callback);
    }

    /**
     * Create a route group for a specific API version using a Subdomain.
     * Generates routes like: v1.yourdomain.com/users
     * * @param string $version The version subdomain string (e.g., 'v1' or 'v1.api')
     * @param mixed ...$args Attributes/Middleware and callback
     * @return void
     */
    public static function subdomainVersion(string $version, ...$args): void
    {
        $callback = array_pop($args);
        
        // Apply the subdomain constraint directly
        $attributes = [
            'subdomain' => $version
        ];

        // Check if the next argument is an associative array of attributes (like namespace)
        if (!empty($args) && is_array($args[0]) && (array_keys($args[0]) !== range(0, count($args[0]) - 1))) {
            $attributes = array_merge($attributes, array_shift($args));
        } else {
            // Otherwise, treat any remaining arguments as middleware
            $attributes['middleware'] = $args;
        }
        
        self::group($attributes, $callback);
    }
    
    /**
     * Create a route group with custom attributes
     * * @param array $attributes Group attributes (prefix, subdomain, middleware, namespace)
     * @param callable $callback Callback to register routes
     * @return void
     */
    public static function group(array $attributes, callable $callback): void
    {
        // Normalize middleware to array
        if (isset($attributes['middleware']) && !is_array($attributes['middleware'])) {
            $attributes['middleware'] = [$attributes['middleware']];
        } elseif (!isset($attributes['middleware'])) {
            $attributes['middleware'] = [];
        }
        
        self::$groupStack[] = $attributes;
        
        $callback();
        
        array_pop(self::$groupStack);
    }
    
    /**
     * Apply middleware to a group of routes
     * * @param array|string $middleware Middleware class(es) to apply
     * @param callable $callback Callback to register routes
     * @return void
     */
    public static function middleware($middleware, callable $callback): void
    {
        self::group(['middleware' => $middleware], $callback);
    }
    
    /**
     * Add a route
     * * @param string $method
     * @param string $path
     * @param callable|array $handler
     * @return Route
     */
    protected static function addRoute(string $method, string $path, $handler): Route
    {
        $route = new Route($method, self::applyGroupPrefix($path), $handler);
        
        // Apply group attributes
        if (!empty(self::$groupStack)) {
            foreach (self::$groupStack as $group) {
                if (isset($group['subdomain'])) {
                    $route->subdomain($group['subdomain']);
                }
                
                if (isset($group['middleware'])) {
                    $middleware = is_array($group['middleware']) ? $group['middleware'] : [$group['middleware']];
                    foreach ($middleware as $m) {
                        if (!empty($m)) {
                            $route->middleware($m);
                        }
                    }
                }
                
                if (isset($group['namespace']) && is_array($handler)) {
                    $handler[0] = $group['namespace'] . '\\' . $handler[0];
                    $route->handler = $handler;
                }
            }
        }
        
        self::$routes[] = $route;
        
        return $route;
    }
    
    /**
     * Add a route with middleware from arguments
     * Last argument is the handler, everything else is middleware
     * * @param string $method
     * @param string $path
     * @param array $args
     * @return Route
     */
    protected static function addRouteWithMiddleware(string $method, string $path, array $args): Route
    {
        if (empty($args)) {
            throw new \InvalidArgumentException("Route handler is required");
        }
        
        // Last argument is the handler
        $handler = array_pop($args);
        
        // Everything else is middleware
        $middleware = $args;
        
        // Create the route
        $route = self::addRoute($method, $path, $handler);
        
        // Apply middleware
        foreach ($middleware as $m) {
            if (!empty($m)) {
                $route->middleware($m);
            }
        }
        
        return $route;
    }
    
    /**
     * Apply group prefix to path
     * * @param string $path
     * @return string
     */
    protected static function applyGroupPrefix(string $path): string
    {
        $prefix = '';
        
        foreach (self::$groupStack as $group) {
            if (isset($group['prefix'])) {
                // Trim slashes from the group prefix and add one at the start
                $prefix .= '/' . trim($group['prefix'], '/');
            }
        }
        
        // Trim the current path and combine with prefix
        $path = trim($path, '/');
        $fullPath = $prefix . '/' . $path;
        
        // Final cleanup: ensure it starts with / and remove trailing slashes
        // This turns "" or "/" into just "/"
        $normalized = '/' . trim($fullPath, '/');
        
        return $normalized === '' ? '/' : $normalized;
    }
    
    /**
     * Dispatch the request
     * * @return Response
     */
    public static function dispatch(): Response
    {
        if (!self::$request || !self::$response) {
            throw new \Exception('Router not initialized. Call Router::init() first.');
        }
        
        // $requestMethod = self::$request->method();
        // $requestUri = self::$request->uri();
        // $requestHost = self::$request->host();

        $requestMethod = self::$request->method();
    
        // Normalize the Request URI: Trim trailing slashes unless it's just "/"
        $requestUri = self::$request->uri();
        $requestUri = ($requestUri !== '/') ? rtrim($requestUri, '/') : '/';
        
        $requestHost = self::$request->host();
        
        // Sort routes: literal paths match before parameterized ones
        $sortedRoutes = self::$routes;
        usort($sortedRoutes, function (Route $a, Route $b) {
            $aParams = substr_count($a->path, '{');
            $bParams = substr_count($b->path, '{');
            return $aParams <=> $bParams;
        });
        
        foreach ($sortedRoutes as $route) {
            if ($route->method !== 'ANY' && $route->method !== $requestMethod) {
                continue;
            }
            
            // matchSubdomain now injects params directly into self::$request
            if (!self::matchSubdomain($route, $requestHost)) {
                continue;
            }
            
            $params = self::matchRoute($route->path, $requestUri);
            
            if ($params !== false) {
                // 1. Hydrate the Request object with route parameters
                foreach ($params as $key => $value) {
                    self::$request->setParam($key, $value);
                }

                // 2. Define the core handler (the "destination" of the pipe)
                // Inside Router::dispatch() -> $destination closure
                $destination = function ($request) use ($route, $params) {
                    // 1. Execute the controller/closure
                    $result = self::callHandler($route->handler, $params);
                    
                    // 2. If it's already a Response object, just return it.
                    if ($result instanceof Response) {
                        return $result;
                    }

                    // 3. If it's a string or array, wrap it in the EXISTING response object
                    if (is_string($result)) {
                        return self::$response->setContent($result);
                    } elseif (is_array($result)) {
                        return self::$response->json($result);
                    }
                    
                    // 4. Fallback: Return the response object associated with the Router
                    return self::$response;
                };

                // 3. Build the Middleware Pipeline
                // We reverse the middleware so they execute in the order they were defined
                $pipeline = array_reduce(
                    array_reverse($route->middleware),
                    function ($next, $middleware) use ($params) {
                        return function ($request) use ($next, $middleware, $params) {
                            return self::runMiddleware($middleware, $params, $next);
                        };
                    },
                    $destination
                );

                // 4. Execute the pipeline
                return $pipeline(self::$request);
            }
        }
        
        // 404 Not Found
        self::$response->setStatusCode(404);

        // Check for a custom handler
        if (self::$errorHandler) {
            $result = (self::$errorHandler)(self::$request, self::$response);
            if ($result instanceof Response) {
                return $result;
            }
            if (is_string($result)) {
                return self::$response->setContent($result);
            }
        }

        // Check for an errors.404 view
        if (class_exists('\Framework\Core\View') && \Framework\Core\View::exists('errors.404')) {
            return self::$response->setContent(view('errors.404'));
        }

        self::$response->setContent('404 Not Found');
        
        return self::$response;
    }
    
    /**
     * Run middleware
     * * @param mixed $middleware
     * @param array $params
     * @return mixed
     */
    protected static function runMiddleware($middleware, array $params, callable $next)
    {
        $instance = null;

        // Resolve the middleware instance
        if (is_string($middleware)) {
            if (!class_exists($middleware)) {
                throw new \Exception("Middleware class not found: {$middleware}");
            }
            $instance = self::$container ? self::$container->make($middleware) : new $middleware();
        } elseif (is_object($middleware)) {
            $instance = $middleware;
        }

        // Handle Class-based Middleware
        if ($instance && method_exists($instance, 'handle')) {
            $response = $instance->handle(self::$request, $next);
            
            if (!($response instanceof Response)) {
                throw new \Exception("Middleware [" . get_class($instance) . "] must return a Response object.");
            }
            return $response;
        }

        // Handle Closures/Callables
        if (is_callable($middleware)) {
            return $middleware(self::$request, $next, $params);
        }

        return $next(self::$request);
    }
    
    /**
     * Match subdomain constraint
     * * @param Route $route
     * @param string $host
     * @return bool
     */
    protected static function matchSubdomain(Route $route, string $host): bool
    {
        if (empty($route->subdomain)) {
            return true;
        }

        if (strpos($route->subdomain, '{') !== false) {
            $pattern = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '(?P<$1>[^.]+)', $route->subdomain);
            $pattern = '#^' . $pattern . '#';

            if (preg_match($pattern, $host, $matches)) {
                $subdomainParams = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                foreach ($subdomainParams as $key => $value) {
                    // FIXED: Inject into Request object, not $_GET
                    self::$request->setParam('_subdomain_' . $key, $value);
                }
                return true;
            }
            return false;
        }
        return strpos($host, $route->subdomain) === 0;
    }
    
    
    /**
     * Match a route pattern against a URI
     * * @param string $pattern
     * @param string $uri
     * @return array|false
     */
    protected static function matchRoute(string $pattern, string $uri)
    {
        // Support custom regex in parameters: {name:regex}
        $pattern = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*):(.+?)\}/', '(?P<$1>$2)', $pattern);
        
        // Convert standard route pattern to regex: {name}
        $pattern = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '(?P<$1>[^/]+)', $pattern);
        
        $pattern = '#^' . $pattern . '$#';
        
        if (preg_match($pattern, $uri, $matches)) {
            // Extract named parameters
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            return $params;
        }
        
        return false;
    }
    
    /**
     * Call the route handler
     * * @param callable|array|string $handler
     * @param array $params
     * @return mixed
     */
    protected static function callHandler($handler, array $params)
    {
        // If handler is an array [Controller::class, 'method']
        if (is_array($handler)) {
            [$controller, $method] = $handler;
            
            // Instantiate controller if it's a class name
            if (is_string($controller)) {
                // USE CONTAINER TO INSTANTIATE
                $controller = self::$container ? self::$container->make($controller) : new $controller();
            }
            
            return $controller->{$method}(self::$request, self::$response, $params);
        }
        
        // If handler is a class name string
        if (is_string($handler) && class_exists($handler)) {
            // USE CONTAINER TO INSTANTIATE
            $instance = self::$container ? self::$container->make($handler) : new $handler();
            
            // Try __invoke method first
            if (method_exists($instance, '__invoke')) {
                return $instance(self::$request, self::$response, $params);
            }
            
            // Try handle method
            if (method_exists($instance, 'handle')) {
                return $instance->handle(self::$request, self::$response);
            }
            
            throw new \Exception("Handler class {$handler} must have __invoke or handle method");
        }
        
        // If handler is a callable/closure
        if (is_callable($handler)) {
            return $handler(self::$request, self::$response, $params);
        }
        
        // If handler is an object
        if (is_object($handler)) {
            if (method_exists($handler, '__invoke')) {
                return $handler(self::$request, self::$response, $params);
            }
            
            if (method_exists($handler, 'handle')) {
                return $handler->handle(self::$request, self::$response);
            }
        }
        
        throw new \Exception("Invalid handler type");
    }
    
    /**
     * Clear all routes (useful for testing)
     * * @return void
     */
    public static function clearRoutes(): void
    {
        self::$routes = [];
        self::$groupStack = [];
    }
    
    /**
     * Get all registered routes
     * 
     * @return array
     */
    public static function getRoutes(): array
    {
        return self::$routes;
    }

    /**
     * Get a route by its name
     * 
     * @param string $name
     * @return Route|null
     */
    public static function getRouteByName(string $name): ?Route
    {
        foreach (self::$routes as $route) {
            if ($route->name === $name) {
                return $route;
            }
        }
        
        return null;
    }
    
    /**
     * Load all route files from a directory recursively.
     * * @param string $directory
     * @return void
     */
    public static function loadRoutesFrom(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        // Create a recursive iterator to find all .php files in subfolders
        $iterator = new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new \RecursiveIteratorIterator($iterator);

        foreach ($files as $file) {
            // Only load PHP files
            if ($file->isFile() && $file->getExtension() === 'php') {
                require_once $file->getPathname();
            }
        }
    }
    
    /**
     * Load routes from a specific file
     * * @param string $filePath Path to the route file
     * @return void
     */
    public static function loadRouteFile(string $filePath): void
    {
        if (!file_exists($filePath)) {
            throw new \Exception("Route file not found: {$filePath}");
        }
        
        require_once $filePath;
    }
    
    /**
     * Register framework internal routes (assets, etc.)
     */
    protected static function registerInternalRoutes(): void
    {
        self::get('/_framework/websocket.js', function(\Framework\Core\Http\Request $request, \Framework\Core\Http\Response $response) {
            $path = dirname(__DIR__) . '/WebSocket/assets/websocket.js';
            $response->file($path, 'application/javascript');
        });
    }
}


