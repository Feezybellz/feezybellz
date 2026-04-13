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
            
            // Register Global Middleware in Router
            Router::globalMiddleware($this->middleware);

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
        // 1. Register Core Services
        $this->registerServices();

        // 2. Load Events
        Dispatcher::register(require $this->app->basePath('config/events.php'));
    }

    /**
     * Register core services in the container
     */
    protected function registerServices(): void
    {
        // For now, we'll register Session directly here. 
        // Later we can use ServiceProviders for a more modular approach.
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
