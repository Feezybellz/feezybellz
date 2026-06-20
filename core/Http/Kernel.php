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
     * Route-specific middleware that can be assigned to individual routes
     */
    protected array $routeMiddleware = [
        'csrf' => \App\Middleware\CsrfMiddleware::class,
        'cors' => \App\Middleware\CorsMiddleware::class,
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

            // Built-in framework asset routes
            Router::get('/websocket.js', function (\Framework\Core\Http\Request $request, \Framework\Core\Http\Response $response) {
                $path = base_path('core/WebSocket/websocket.js');
                if (file_exists($path)) {
                    $response->setHeader('Content-Type', 'application/javascript');
                    $response->setHeader('Cache-Control', 'no-cache, must-revalidate'); // Ensure changes load immediately
                    $response->setContent(file_get_contents($path));
                    return $response;
                }
                $response->setStatusCode(404);
                $response->setContent('WebSocket Client Script not found.');
                return $response;
            });

            // Load application routes
            Router::loadRoutesFrom($this->app->basePath('routes'));
            
            $result = Router::dispatch($request, $response);

            return ($result instanceof Response) ? $result : $response->setContent($result);
            
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
    }

    /**
     * Register core services in the container
     */
    protected function registerServices(): void
    {
        $this->app->singleton(SessionDriverInterface::class, PHPSessionDriver::class);
        $this->app->singleton(Session::class);
    }

    /**
     * Apply global security headers to the response
     */
    public function terminate(Request $request, Response $response): void
    {
        $response->header('X-Frame-Options', 'DENY');
        $response->header('X-Content-Type-Options', 'nosniff');
        $response->header('X-XSS-Protection', '1; mode=block');
        $response->header('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->header('Permissions-Policy', 'camera=(), microphone=(), geolocation=(self)');
    }
}
