<?php

namespace Framework\Core;

class View
{
    protected $viewsPath;
    
    /** @var self|null Singleton instance for static calls */
    protected static $instance = null;

    public function __construct(string $viewsPath = null)
    {
        $this->viewsPath = $viewsPath ?? dirname(__DIR__) . '/views';
    }

    /**
     * Get the global singleton instance
     */
    protected static function getInstance(): self
    {
        if (!static::$instance) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    /**
     * Captures static calls like View::render()
     */
    public static function __callStatic($method, $args)
    {
        return static::getInstance()->$method(...$args);
    }

    /**
     * Captures instance calls like $view->render()
     * This redirects calls to protected internal methods prefixed with '_'
     */
    public function __call($method, $args)
    {
        $internalMethod = '_' . $method;
        if (method_exists($this, $internalMethod)) {
            return $this->$internalMethod(...$args);
        }
        
        throw new \BadMethodCallException("Method {$method} does not exist.");
    }
    
    /**
     * Render a view with data
     * 
     * @param string $_viewName View name in dot notation (e.g., 'pages.home')
     * @param array $data Data to extract into the view
     * @return string
     */
    protected function _render(string $_viewName, array $data = []): string
    {
        $path = $this->_resolveViewPath($_viewName);
        
        if (!file_exists($path)) {
            throw new \Exception("View not found: {$_viewName} (Path: {$path})");
        }
        
        extract($data, EXTR_SKIP);
        
        ob_start();
        include $path;
        return ob_get_clean();
    }
    
    /**
     * Display a view directly
     * 
     * @param string $_viewName View name in dot notation
     * @param array $data Data to extract into the view
     * @return void
     */
    protected function _display(string $_viewName, array $data = []): void
    {
        echo $this->_render($_viewName, $data);
    }
    
    /**
     * Resolve view path from dot notation
     * 
     * @param string $name View name (e.g., 'pages.home')
     * @return string
     */
    protected function _resolveViewPath(string $name): string
    {
        $path = str_replace('.', DIRECTORY_SEPARATOR, $name);
        return $this->viewsPath . DIRECTORY_SEPARATOR . $path . '.php';
    }
    
    protected function _path(string $name): string
    {
        return $this->_resolveViewPath($name);
    }

    /**
     * Check if a view exists
     * 
     * @param string $name View name in dot notation
     * @return bool
     */
    protected function _exists(string $name): bool
    {
        return file_exists($this->_resolveViewPath($name));
    }
}
