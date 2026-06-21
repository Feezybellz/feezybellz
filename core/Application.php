<?php

namespace Framework\Core;
use Framework\Core\Exceptions\Handler;
use Framework\Core\Container\Container;

class Application extends Container
{
    protected static $basePath;
    protected static $customPublicPath = null;
    protected array $serviceProviders = [];
    protected bool $isBooted = false;
    
    public function __construct(string $basePath)
    {
        self::$basePath = $basePath;
        (new Handler())->register();
    }

    /**
     * Register configured service providers from config/app.php
     */
    public function registerConfiguredProviders(): void
    {
        $appConfigPath = $this->configPath('app.php');
        $providers = [];

        if (file_exists($appConfigPath)) {
            $appConfig = require $appConfigPath;
            $providers = $appConfig['providers'] ?? [];
        }

        foreach ($providers as $providerClass) {
            $this->registerProvider(new $providerClass($this));
        }
    }

    /**
     * Register a single service provider
     */
    public function registerProvider(\Framework\Core\Support\ServiceProvider $provider): void
    {
        $provider->register();
        $this->serviceProviders[] = $provider;
    }

    /**
     * Boot all registered service providers
     */
    public function boot(): void
    {
        if ($this->isBooted) {
            return;
        }

        foreach ($this->serviceProviders as $provider) {
            $provider->boot();
        }

        $this->isBooted = true;
    }
    
    /**
     * Get the base path
     * 
     * @param string $path
     * @return string
     */
    public static function basePath(string $path = ''): string
    {
        return self::$basePath . ($path ? DIRECTORY_SEPARATOR . $path : '');
    }
    
    /**
     * Get the app path
     * 
     * @param string $path
     * @return string
     */
    public static function appPath(string $path = ''): string
    {
        return self::basePath('app') . ($path ? DIRECTORY_SEPARATOR . $path : '');
    }
    
    /**
     * Get the config path
     * 
     * @param string $path
     * @return string
     */
    public static function configPath(string $path = ''): string
    {
        return self::basePath('config') . ($path ? DIRECTORY_SEPARATOR . $path : '');
    }
    
    /**
     * Get the database path
     * 
     * @param string $path
     * @return string
     */
    public static function databasePath(string $path = ''): string
    {
        return self::basePath('database') . ($path ? DIRECTORY_SEPARATOR . $path : '');
    }
    
    /**
     * Set a custom public path
     * 
     * @param string $path
     */
    public static function usePublicPath(string $path): void
    {
        self::$customPublicPath = rtrim($path, '/\\');
    }

    /**
     * Get the public path
     * 
     * @param string $path
     * @return string
     */
    public static function publicPath(string $path = ''): string
    {
        $basePublic = self::$customPublicPath ?: self::basePath('public');
        return $basePublic . ($path ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : '');
    }
}
