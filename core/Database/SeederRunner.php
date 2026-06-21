<?php

namespace Framework\Core\Database;

class SeederRunner
{
    protected $seedersPath;
    protected $connection;
    
    public function __construct(string $seedersPath = null, string $connection = null)
    {
        $this->connection = $connection;
        
        if ($seedersPath === null) {
            $cwd = getcwd();
            $this->seedersPath = is_dir($cwd . '/database/seeders') 
                ? $cwd . '/database/seeders' 
                : dirname(__DIR__, 2) . '/database/seeders';
        } else {
            $this->seedersPath = rtrim($seedersPath, '/');
        }
    }

    public function run(string $specificSeeder = null): array
    {
        $seeded = [];
        $files = $this->getSeederFiles($specificSeeder);
        
        foreach ($files as $file) {
            $name = basename($file, '.php');

            $previousConnection = DB::getDefaultConnectionName();
            if ($this->connection) {
                DB::setDefaultConnection($this->connection);
            }

            require_once $file;
            
            $className = $this->getClassName($name);
            if (!class_exists($className)) {
                continue;
            }
            
            $instance = new $className();
            
            if ($this->connection && method_exists($instance, 'setConnection')) {
                $instance->setConnection($this->connection);
            }

            try {
                $instance->run();
            } catch (\Exception $e) {
                if (php_sapi_name() === 'cli') {
                    echo "\033[31m  [ERROR] {$name} - " . $e->getMessage() . "\033[0m\n";
                } else {
                    throw $e;
                }
            }

            if ($this->connection) {
                DB::setDefaultConnection($previousConnection);
            }

            $seeded[] = $name;
        }
        return $seeded;
    }

    protected function getSeederFiles(string $specificSeeder = null): array
    {
        if (!is_dir($this->seedersPath)) return [];
        
        if ($specificSeeder) {
            $file = $this->seedersPath . '/' . $specificSeeder . '.php';
            return file_exists($file) ? [$file] : [];
        }
        
        $files = glob($this->seedersPath . '/*.php');
        sort($files);
        return $files;
    }

    protected function getClassName(string $filename): string
    {
        $name = preg_replace('/^\d{14}_/', '', $filename);
        return str_replace('_', '', ucwords($name, '_'));
    }
}
