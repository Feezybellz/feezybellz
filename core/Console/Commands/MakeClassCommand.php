<?php

namespace Framework\Core\Console\Commands;

use Framework\Core\Console\Command;

class MakeClassCommand extends Command
{
    public function execute(): void
    {
        $name = $this->argument(0);
        $directory = $this->option('dir', 'Services');
        
        if (!$name) {
            $this->error('Class name is required.');
            $this->info('Usage: php console make:class <name> [--dir=DirectoryName]');
            return;
        }
        
        $basePath = dirname(dirname(dirname(__DIR__))) . '/app/' . $directory;
        
        if (!is_dir($basePath)) {
            mkdir($basePath, 0755, true);
        }
        
        $filePath = "{$basePath}/{$name}.php";
        
        if (file_exists($filePath)) {
            $this->error("Class already exists: {$name} in app/{$directory}");
            return;
        }
        
        $template = $this->getTemplate($name, $directory);
        
        file_put_contents($filePath, $template);
        
        $this->success("Class created: app/{$directory}/{$name}.php");
    }
    
    /**
     * Get class template
     * 
     * @param string $className
     * @param string $directory
     * @return string
     */
    protected function getTemplate(string $className, string $directory): string
    {
        $namespace = "App\\" . str_replace('/', '\\', $directory);
        
        return <<<PHP
<?php

namespace {$namespace};

class {$className}
{
    /**
     * Create a new instance
     * 
     * @return void
     */
    public function __construct()
    {
        //
    }
}

PHP;
    }
}
