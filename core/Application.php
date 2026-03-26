<?php

namespace Framework\Core;
use Framework\Core\Exceptions\Handler;
use Framework\Core\Container\Container;

class Application extends Container
{
    protected static $basePath;
    
    public function __construct(string $basePath)
    {
        self::$basePath = $basePath;
        (new Handler())->register();
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
     * Get the public path
     * 
     * @param string $path
     * @return string
     */
    public static function publicPath(string $path = ''): string
    {
        return self::basePath('public') . ($path ? DIRECTORY_SEPARATOR . $path : '');
    }
}
