<?php

namespace Framework\Core\Http;

use Framework\Core\Application;
use Framework\Core\Container\Container;
use Framework\Core\Routing\Router;
use Framework\Core\Events\Dispatcher;

class Kernel
{
    protected $app;

    /**
     * Global middleware that runs on every request
     */
    protected array $middleware = [
        \App\Middleware\DatabaseManager::class,
        \App\Middleware\TenantMiddleware::class,
    ];

    /**
     * Framework-shipped middleware aliases.
     *
     * These are the "out of the box" middleware names a developer can attach
     * to a route or group without writing a line of PHP — e.g. `'csrf'`,
     * `'cors:public'`, `'security:strict'`.
     *
     * Each alias points to a class in `core/Http/Middleware/` whose behavior
     * is configured by a file in `config/` of the same name (cors.php,
     * csrf.php, security_headers.php, etc.). Apps tune behavior by editing
     * those configs, not by editing this array.
     *
     * To register *additional* aliases (e.g. an app's own AuthMiddleware),
     * use `app/Middleware/aliases.php` — it loads after this array, so app
     * aliases extend or override these defaults without touching framework
     * source.
     */
    protected array $routeMiddleware = [
        'auth'     => \Framework\Core\Http\Middleware\Authenticate::class,
        'csrf'     => \Framework\Core\Http\Middleware\CsrfMiddleware::class,
        'cors'     => \Framework\Core\Http\Middleware\CorsMiddleware::class,
        'security' => \Framework\Core\Http\Middleware\SecurityHeadersMiddleware::class,
        'throttle' => \Framework\Core\Http\Middleware\ThrottleRequests::class,
        'waf'      => \Framework\Core\Http\Middleware\WafMiddleware::class,
    ];

    public function __construct(Application $app)
    {
        $this->app = $app;
        Container::setInstance($app);
    }

    /**
     * Handle the incoming HTTP request
     */
    public function handle(Request $request): Response
    {
        try {
            $this->bootstrap($request);

            // Give this request an ID and stash it on Log so every log line
            // emitted while handling this request carries it. Client can
            // supply X-Request-Id to correlate with upstream traces; if
            // absent, we generate one.
            $requestId = (string) ($request->header('X-Request-Id') ?: bin2hex(random_bytes(8)));
            \Framework\Core\Logging\Log::setContext(['request_id' => $requestId]);
            $request->setParam('_request_id', $requestId);
            
            // Bind the current request into the container so downstream
            // services (Auth guards, FormRequests, custom middleware) all
            // resolve the same instance.
            $this->app->instance(Request::class, $request);

            $response = new Response();
            Router::init($request, $response);
            
            // Register Middleware in Router
            Router::globalMiddleware($this->middleware);
            Router::aliasMiddleware($this->routeMiddleware);

            // Load App Middleware Aliases if the file exists
            $aliasesFile = $this->app->basePath('app/Middleware/aliases.php');
            if (file_exists($aliasesFile)) {
                require_once $aliasesFile;
            }

            // Built-in framework asset routes (always fresh — not cached).
            Router::get('/websocket.js', function (\Framework\Core\Http\Request $request, \Framework\Core\Http\Response $response) {
                $path = base_path('core/WebSocket/websocket.js');
                if (file_exists($path)) {
                    $response->setHeader('Content-Type', 'application/javascript');
                    $response->setHeader('Cache-Control', 'no-cache, must-revalidate');
                    $response->setContent(file_get_contents($path));
                    return $response;
                }
                $response->setStatusCode(404);
                $response->setContent('WebSocket Client Script not found.');
                return $response;
            });

            // Application routes: cache-first. If `php console route:cache` has
            // been run, `bootstrap/cache/routes.php` exists and we bypass the
            // recursive file walk entirely. Otherwise fall back to loading
            // from disk. Framework-internal routes above are always added
            // fresh so they can evolve between framework versions without
            // invalidating a user's cache.
            $cachedRoutes = $this->app->basePath('bootstrap/cache/routes.php');
            if (file_exists($cachedRoutes)) {
                Router::loadCachedRoutes($cachedRoutes);
                // Optional dev-mode staleness check: warn (once per request)
                // if any file under routes/ is newer than the cache.
                if (function_exists('config') && config('app.debug')) {
                    $this->warnIfCacheIsStale($cachedRoutes);
                }
            } else {
                Router::loadRoutesFrom($this->app->basePath('routes'));
            }
            
            $result = Router::dispatch($request, $response);
            $finalResponse = ($result instanceof Response) ? $result : $response->setContent($result);
            // Echo the request id back so callers can correlate.
            $finalResponse->header('X-Request-Id', $requestId);
            return $finalResponse;
            
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    /**
     * Bootstrap the application (Services, Events)
     */
    protected function bootstrap(Request $request): void
    {
        // 1. Register Core Services manually (Legacy)
        $this->registerServices();

        // 2. Register & Boot all Service Providers!
        $this->app->registerConfiguredProviders();
        $this->app->boot();

        // 3. Load Events
        $eventsPath = $this->app->basePath('config/events.php');
        if (file_exists($eventsPath)) {
            Dispatcher::register(require $eventsPath);
        }

        // 4. N+1 detection in dev. Zero cost in production because we
        //    never call install() when APP_DEBUG=false.
        if (function_exists('config') && config('app.debug')) {
            \Framework\Core\Database\NPlusOneDetector::install([
                'threshold' => (int) (config('app.n_plus_one_threshold') ?? 10),
                'throw'     => (bool) (config('app.n_plus_one_throws') ?? false),
            ]);
        }
    }

    /**
     * Register core services in the container
     */
    protected function registerServices(): void
    {
        $this->app->singleton(SessionDriverInterface::class, PHPSessionDriver::class);
        $this->app->singleton(Session::class);
        $this->app->singleton(\Framework\Core\Auth\GuardManager::class);
    }

    /**
     * Apply global security headers to the response
     */
    public function terminate(Request $request, Response $response): void
    {
        // Security headers are now managed via SecurityHeadersMiddleware
    }

    /**
     * Emit an E_USER_WARNING when the routes cache is older than any file
     * under routes/. Only runs in debug mode; the mtime scan is cheap but
     * pointless in production where the cache is authoritative.
     */
    protected function warnIfCacheIsStale(string $cachedRoutes): void
    {
        $cacheMtime = filemtime($cachedRoutes) ?: 0;
        $routesDir = $this->app->basePath('routes');
        if (!is_dir($routesDir)) return;

        try {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($routesDir, \RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($it as $file) {
                if ($file->isFile() && $file->getExtension() === 'php'
                    && $file->getMTime() > $cacheMtime) {
                    trigger_error(
                        "Route cache is stale: {$file->getPathname()} was modified after the cache was built. "
                        . "Run `php console route:cache` to refresh.",
                        E_USER_WARNING
                    );
                    return; // one warning per request is enough
                }
            }
        } catch (\Throwable $e) {
            // Nothing to do — the check is best-effort.
        }
    }
}
