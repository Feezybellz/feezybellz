<?php

namespace Framework\Core\Routing;

use Framework\Core\Http\Request;
use Framework\Core\Http\Response;
use Framework\Core\Container\Container;

class Router
{
    protected static $routes = [];
    protected static $globalMiddleware = [];
    protected static $middlewareAliases = [];
    protected static $groupStack = [];
    protected static $request = null;
    protected static $response = null;
    protected static $container = null;
    protected static $errorHandler = null;
    protected static $routesSorted = false;
    protected static $reflectionCache = [];

    /**
     * Cap the reflection cache so long-running workers don't accumulate
     * entries forever. When we're about to exceed the cap, drop the oldest
     * half (LRU-ish via array_slice — cheap and good enough at these sizes).
     */
    protected const REFLECTION_CACHE_LIMIT = 512;
    protected static $parsedAppDomains = null;

    /**
     * Register global middleware
     */
    public static function globalMiddleware(array $middleware): void
    {
        self::$globalMiddleware = array_merge(self::$globalMiddleware, $middleware);
    }

    /**
     * Register middleware aliases in bulk
     */
    public static function aliasMiddleware(array $aliases): void
    {
        self::$middlewareAliases = array_merge(self::$middlewareAliases, $aliases);
    }

    /**
     * Register a single middleware alias
     */
    public static function registerAlias(string $name, string $class): void
    {
        self::$middlewareAliases[$name] = $class;
    }

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
        
        $route->compiledPattern = self::compilePattern($route->path);
        self::$routesSorted = false;
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
        // Strip :port from host before matching — both subdomain/exact-host
        // comparisons should ignore the port the client used.
        if (($colonPos = strpos($requestHost, ':')) !== false) {
            $requestHost = substr($requestHost, 0, $colonPos);
        }

        // Sort by specificity: literal first, then parameterized, then wildcards.
        if (!self::$routesSorted) {
            usort(self::$routes, function (Route $a, Route $b) {
                return self::routeSpecificity($a) <=> self::routeSpecificity($b);
            });
            self::$routesSorted = true;
        }

        foreach (self::$routes as $route) {
            if ($route->method !== 'ANY' && $route->method !== $requestMethod) {
                continue;
            }

            // matchSubdomain now returns the captured params (or null) rather
            // than mutating Request mid-iteration. This avoids stale
            // subdomain params lingering on the request after a route's
            // subdomain matched but its URI did not.
            $subdomainParams = self::matchSubdomain($route, $requestHost);
            if ($subdomainParams === null) {
                continue;
            }

            $params = self::matchRoute($route->compiledPattern, $requestUri);

            if ($params !== false) {
                // Merge subdomain captures first, then URI captures (URI wins
                // on key collision, which is the standard precedence).
                $params = array_merge($subdomainParams, $params);

                // 1. Hydrate the Request object with route parameters
                foreach ($params as $key => $value) {
                    self::$request->setParam($key, $value);
                }

                // 2. Define the core handler (the "destination" of the pipe)
                // Inside Router::dispatch() -> $destination closure
                $destination = function ($request) use ($route) {
                    // 1. Execute the controller/closure (Pass ALL params including subdomains)
                    $result = self::callHandler($route->handler, self::$request->routeParams());
                    
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
                // We combine Global middleware with Route-specific middleware
                $allMiddleware = array_merge(self::$globalMiddleware, $route->middleware);

                // We reverse the middleware so they execute in the order they were defined
                $pipeline = array_reduce(
                    array_reverse($allMiddleware),
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
    protected static function runMiddleware($middleware, array $routeParams, callable $next)
    {
        $instance = null;
        $middlewareParams = [];

        // Parse middleware string for parameters (e.g., "throttle:60,1")
        if (is_string($middleware) && strpos($middleware, ':') !== false) {
            list($middleware, $paramString) = explode(':', $middleware, 2);
            $middlewareParams = explode(',', $paramString);
        }

        // Resolve aliases
        if (is_string($middleware) && isset(self::$middlewareAliases[$middleware])) {
            $middleware = self::$middlewareAliases[$middleware];
        }

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
            $response = $instance->handle(self::$request, $next, $middlewareParams);
            
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
    protected static function getAppDomains(): array
    {
        if (self::$parsedAppDomains === null) {
            self::$parsedAppDomains = array_filter(array_map('trim', explode(',', env('APP_DOMAIN', ''))));
        }
        return self::$parsedAppDomains;
    }

    /**
     * Try to match a route's subdomain constraint against the request host.
     *
     * Returns:
     *   - null when the route does NOT match (so dispatch can move on).
     *   - array of captured subdomain params (possibly empty) on a match.
     *
     * Important: this method is intentionally pure — it does NOT mutate
     * Request. The previous implementation set route params as a side effect
     * of the constraint check, which meant a route that matched the subdomain
     * but failed the URI match would leave stale params on the Request that
     * a later route's handler could observe.
     */
    protected static function matchSubdomain(Route $route, string $host): ?array
    {
        $appDomains = self::getAppDomains();

        // Global routes (no subdomain constraint).
        if (empty($route->subdomain)) {
            // If APP_DOMAIN is set, lock global routes to the apex/www host
            // so a global route doesn't accidentally serve traffic for an
            // unrelated subdomain ("subdomain bleeding").
            if (!empty($appDomains)) {
                foreach ($appDomains as $appDomain) {
                    if ($host === $appDomain || $host === 'www.' . $appDomain) {
                        return [];
                    }
                }
                return null;
            }
            return [];
        }

        $expectedSubdomain = $route->subdomain;

        // Try a fully-qualified expected host against the request host.
        $tryMatch = static function (string $expectedFull) use ($host): ?array {
            if (strpos($expectedFull, '{') !== false) {
                $pattern = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '(?P<$1>[^.]+)', $expectedFull);
                $pattern = '#^' . $pattern . '$#';
                if (preg_match($pattern, $host, $matches)) {
                    return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                }
                return null;
            }
            return $host === $expectedFull ? [] : null;
        };

        if (!empty($appDomains)) {
            foreach ($appDomains as $domain) {
                $expectedFull = $expectedSubdomain;
                if ($domain !== '' && strpos($expectedFull, $domain) === false) {
                    $expectedFull = rtrim($expectedFull, '.') . '.' . ltrim($domain, '.');
                }
                $captured = $tryMatch($expectedFull);
                if ($captured !== null) {
                    return $captured;
                }
            }
            return null;
        }

        // No APP_DOMAIN configured — fall back to bare-subdomain matching.
        if (strpos($expectedSubdomain, '{') !== false) {
            $pattern = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '(?P<$1>[^.]+)', $expectedSubdomain);
            $pattern = '#^' . $pattern . '\.#';
            if (preg_match($pattern, $host, $matches)) {
                return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            }
            return null;
        }

        if ($host === $expectedSubdomain
            || strpos($host, rtrim($expectedSubdomain, '.') . '.') === 0) {
            return [];
        }
        return null;
    }
    
    
    // Compile a route path into a PCRE pattern.
    //
    // Wildcard semantics:
    //   /prefix/*  → matches /prefix, /prefix/, /prefix/anything, /prefix/a/b/c.
    //                The trailing /* makes the slash itself optional so the
    //                bare prefix matches too.
    //   /prefix*   → matches /prefix, /prefixXYZ, /prefix-foo, /prefix/anything.
    //                * is a "match zero or more of anything" wildcard, including /.
    //   *  alone   → matches everything.
    //   /a/* /b    → matches /a/x/b and /a/x/y/b (greedy .*) — the space here
    //                is only to keep this comment block valid.
    //
    // Parameter semantics:
    //   {name}     → captures one URL segment (no /).
    //   {name:re}  → captures whatever the inline regex matches.
    protected static function compilePattern(string $pattern): string
    {
        // Trailing /* is a common "match prefix and anything under it" shorthand.
        // Rewrite it so the prefix alone also matches (after trailing-slash strip).
        if (substr($pattern, -2) === '/*') {
            $pattern = substr($pattern, 0, -2) . '(?:/.*)?';
        }

        // Any remaining * is a "match anything" wildcard.
        if (strpos($pattern, '*') !== false) {
            $pattern = str_replace('*', '.*', $pattern);
        }

        $pattern = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*):(.+?)\}/', '(?P<$1>$2)', $pattern);
        $pattern = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '(?P<$1>[^/]+)', $pattern);
        return '#^' . $pattern . '$#';
    }

    /**
     * Specificity score for the dispatch-time sort.
     * Lower wins. Tie-breakers:
     *   - Literal routes beat parameterized routes.
     *   - Parameterized routes beat any wildcard route.
     *   - Among wildcard routes, a longer literal prefix wins
     *     (so `/config/users/*` is tried before `/config/*`).
     */
    protected static function routeSpecificity(Route $route): int
    {
        $hasWildcard = strpos($route->path, '*') !== false;
        $paramCount = substr_count($route->path, '{');

        if ($hasWildcard) {
            // Push wildcards to the back of the line.
            // Longer literal-prefix-before-* sorts lower (= tried first).
            $firstStarPos = strpos($route->path, '*');
            return 10000 + ($paramCount * 100) - $firstStarPos;
        }

        return $paramCount;
    }

    protected static function matchRoute(string $pattern, string $uri)
    {
        if (preg_match($pattern, $uri, $matches)) {
            // Extract named parameters
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            return $params;
        }
        
        return false;
    }
    
    /**
     * Resolve dependencies for a route handler using Reflection
     */
    protected static function resolveDependencies($callable, array $routeParams): array
    {
        $dependencies = [];
        $cacheKey = is_array($callable)
            ? (is_object($callable[0]) ? get_class($callable[0]) : $callable[0]) . '::' . $callable[1]
            : (is_object($callable) && !$callable instanceof \Closure ? get_class($callable) . '::__invoke' : spl_object_id($callable));

        if (!isset(self::$reflectionCache[$cacheKey])) {
            if (count(self::$reflectionCache) >= self::REFLECTION_CACHE_LIMIT) {
                // Bounded cache: drop the oldest half. Cheap at these sizes.
                self::$reflectionCache = array_slice(
                    self::$reflectionCache, self::REFLECTION_CACHE_LIMIT / 2, null, true
                );
            }
            self::$reflectionCache[$cacheKey] = is_array($callable)
                ? new \ReflectionMethod($callable[0], $callable[1])
                : (is_object($callable) && !$callable instanceof \Closure
                    ? new \ReflectionMethod($callable, '__invoke')
                    : new \ReflectionFunction($callable));
        }
        $reflection = self::$reflectionCache[$cacheKey];

        foreach ($reflection->getParameters() as $param) {
            $name = $param->getName();
            $type = $param->getType();

            if ($type && !$type->isBuiltin()) {
                $className = $type->getName();
                if ($className === \Framework\Core\Http\Request::class) {
                    $dependencies[] = self::$request;
                } elseif ($className === \Framework\Core\Http\Response::class) {
                    $dependencies[] = self::$response;
                } elseif (class_exists(\Framework\Core\Http\FormRequest::class)
                          && is_subclass_of($className, \Framework\Core\Http\FormRequest::class)) {
                    // FormRequest subclasses take a Request in their
                    // constructor; feed them the CURRENT request rather
                    // than letting the container build a fresh one.
                    $dependencies[] = new $className(self::$request);
                } elseif (self::$container && self::$container->has($className)) {
                    $dependencies[] = self::$container->make($className);
                } else {
                    $dependencies[] = self::$container ? self::$container->make($className) : new $className();
                }
            } elseif (array_key_exists($name, $routeParams)) {
                $dependencies[] = $routeParams[$name];
            } elseif ($name === 'request') {
                $dependencies[] = self::$request;
            } elseif ($name === 'response') {
                $dependencies[] = self::$response;
            } elseif ($name === 'params') {
                $dependencies[] = $routeParams;
            } elseif ($param->isDefaultValueAvailable()) {
                $dependencies[] = $param->getDefaultValue();
            } else {
                throw new \Exception("Cannot resolve dependency for [\${$name}] in route handler.");
            }
        }

        return $dependencies;
    }

    /**
     * Call the handler with automatic dependency injection
     * 
     * @param callable|array|string $handler
     * @param array $params
     * @return mixed
     */
    protected static function callHandler($handler, array $params)
    {
        // If handler is an array [Controller::class, 'method']
        if (is_array($handler)) {
            [$controller, $method] = $handler;
            
            if (is_string($controller)) {
                $controller = self::$container ? self::$container->make($controller) : new $controller();
            }
            
            $args = self::resolveDependencies([$controller, $method], $params);
            return $controller->{$method}(...$args);
        }
        
        // If handler is a class name string
        if (is_string($handler) && class_exists($handler)) {
            $instance = self::$container ? self::$container->make($handler) : new $handler();
            
            if (method_exists($instance, '__invoke')) {
                $args = self::resolveDependencies([$instance, '__invoke'], $params);
                return $instance(...$args);
            }
            
            if (method_exists($instance, 'handle')) {
                $args = self::resolveDependencies([$instance, 'handle'], $params);
                return $instance->handle(...$args);
            }
            
            throw new \Exception("Handler class {$handler} must have __invoke or handle method");
        }
        
        // If handler is a callable/closure
        if (is_callable($handler)) {
            $args = self::resolveDependencies($handler, $params);
            return $handler(...$args);
        }
        
        // If handler is an object
        if (is_object($handler)) {
            if (method_exists($handler, '__invoke')) {
                $args = self::resolveDependencies([$handler, '__invoke'], $params);
                return $handler(...$args);
            }
            
            if (method_exists($handler, 'handle')) {
                $args = self::resolveDependencies([$handler, 'handle'], $params);
                return $handler->handle(...$args);
            }
        }
        
        throw new \Exception("Invalid handler type");
    }
    
    /**
     * Reset router state. Used by tests and worker reset hooks.
     * Clears registered routes, the group stack, the sort flag, the
     * reflection cache, and the parsed-APP_DOMAIN cache so env changes
     * between tests are respected.
     */
    public static function clearRoutes(): void
    {
        self::$routes = [];
        self::$groupStack = [];
        self::$routesSorted = false;
        self::$reflectionCache = [];
        self::$parsedAppDomains = null;
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

    // ─── Route caching ──────────────────────────────────────────────────

    /**
     * Serializable view of the current route table. Only user routes are
     * included — framework-internal routes (like `/_framework/*`) are
     * always added fresh by init() so they can evolve between framework
     * versions without invalidating a user's cache.
     *
     * Rejects Closure handlers and object middleware — the point of the
     * cache is to be a static PHP array that var_export() can dump into a
     * file, and closures aren't serializable that way. When a route uses
     * a closure, the developer must convert it to
     * `[Controller::class, 'method']` or accept that route caching is off.
     *
     * @return array{routes: array, generated_at: string}
     * @throws \RuntimeException when a route uses a non-serializable handler.
     */
    public static function exportRoutesForCache(): array
    {
        $out = [];
        foreach (self::$routes as $route) {
            // Skip framework-internal routes.
            if (strpos($route->path, '/_framework/') === 0) {
                continue;
            }

            if ($route->handler instanceof \Closure) {
                throw new \RuntimeException(
                    "Cannot cache routes: [{$route->method} {$route->path}] uses a Closure handler. "
                    . "Convert to [Controller::class, 'method'] handler shape to enable caching."
                );
            }

            // Closure/object middleware breaks the export too.
            foreach ($route->middleware as $m) {
                if ($m instanceof \Closure || (is_object($m) && !is_string($m))) {
                    throw new \RuntimeException(
                        "Cannot cache routes: [{$route->method} {$route->path}] has "
                        . "a Closure or object middleware. Register the middleware as a "
                        . "class name / alias string instead."
                    );
                }
            }

            $out[] = [
                'method'          => $route->method,
                'path'            => $route->path,
                'handler'         => $route->handler,
                'subdomain'       => $route->subdomain,
                'middleware'      => $route->middleware,
                'name'            => $route->name,
                'compiledPattern' => $route->compiledPattern,
            ];
        }

        return [
            'routes'       => $out,
            'generated_at' => date('c'),
        ];
    }

    /**
     * Restore routes from a `route:cache` artifact. Appends to whatever's
     * already in the table (typically just the framework-internal routes
     * added by init()); does NOT clear.
     */
    public static function loadCachedRoutes(string $cachePath): int
    {
        if (!file_exists($cachePath)) {
            return 0;
        }
        $data = require $cachePath;
        if (!is_array($data) || !isset($data['routes']) || !is_array($data['routes'])) {
            return 0;
        }

        $loaded = 0;
        foreach ($data['routes'] as $r) {
            $route = new Route((string) $r['method'], (string) $r['path'], $r['handler']);
            $route->subdomain       = $r['subdomain']       ?? null;
            $route->middleware      = $r['middleware']      ?? [];
            $route->name            = $r['name']            ?? null;
            $route->compiledPattern = $r['compiledPattern'] ?? self::compilePattern($route->path);
            self::$routes[] = $route;
            $loaded++;
        }
        self::$routesSorted = false;
        return $loaded;
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


