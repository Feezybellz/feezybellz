<?php

namespace Framework\Core\Console;

abstract class Command
{
    protected $argv;
    protected $arguments = [];
    protected $options = [];
    
    public function __construct(array $argv)
    {
        $this->argv = $argv;
        $this->parseArguments();
    }
    
    /**
     * Execute the command
     * 
     * @return void
     */
    abstract public function execute(): void;
    
    /**
     * Parse command line arguments
     * 
     * @return void
     */
    protected function parseArguments(): void
    {
        for ($i = 2; $i < count($this->argv); $i++) {
            $arg = $this->argv[$i];
            
            if (strpos($arg, '--') === 0) {
                // Long option
                $parts = explode('=', substr($arg, 2), 2);
                $this->options[$parts[0]] = $parts[1] ?? true;
            } elseif (strpos($arg, '-') === 0) {
                // Short option
                $this->options[substr($arg, 1)] = true;
            } else {
                // Argument
                $this->arguments[] = $arg;
            }
        }
    }
    
    /**
     * Get an argument by index
     * 
     * @param int $index
     * @param mixed $default
     * @return mixed
     */
    protected function argument(int $index, $default = null)
    {
        return $this->arguments[$index] ?? $default;
    }
    
    /**
     * Get an option by key
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function option(string $key, $default = null)
    {
        return $this->options[$key] ?? $default;
    }
    
    /**
     * Display a success message
     * 
     * @param string $message
     * @return void
     */
    protected function success(string $message): void
    {
        if ($this->option('silent')) return;
        echo "\033[32m{$message}\033[0m\n";
    }
    
    /**
     * Display an error message
     * 
     * @param string $message
     * @return void
     */
    protected function error(string $message): void
    {
        if ($this->option('silent')) return;
        echo "\033[31mError: {$message}\033[0m\n";
    }
    
    /**
     * Display an info message
     * 
     * @param string $message
     * @return void
     */
    protected function info(string $message): void
    {
        if ($this->option('silent')) return;
        echo "\033[36m{$message}\033[0m\n";
    }
    
    /**
     * Display a warning message
     * 
     * @param string $message
     * @return void
     */
    protected function warn(string $message): void
    {
        if ($this->option('silent')) return;
        echo "\033[33m{$message}\033[0m\n";
    }
    
    /**
     * Display a plain message
     * 
     * @param string $message
     * @return void
     */
    protected function line(string $message): void
    {
        if ($this->option('silent')) return;
        echo "{$message}\n";
    }
}
