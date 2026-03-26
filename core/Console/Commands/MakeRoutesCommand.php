<?php

namespace Framework\Core\Console\Commands;

use Framework\Core\Console\Command;

class MakeRoutesCommand extends Command
{
    public function execute(): void
    {
        $name = $this->argument(0);
        
        if (!$name) {
            $this->error('Route file name is required.');
            $this->info('Usage: php console make:routes <name>');
            return;
        }
        
        // Ensure name ends with .php
        $filename = str_ends_with($name, '.php') ? $name : "{$name}.php";
        
        $routesPath = dirname(dirname(dirname(__DIR__))) . '/routes';
        $filePath = "{$routesPath}/{$filename}";
        $directory = dirname($filePath);
        
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        
        if (file_exists($filePath)) {
            $this->error("Route file already exists: {$filename}");
            return;
        }
        
        $template = $this->getTemplate($name);
        
        file_put_contents($filePath, $template);
        
        $this->success("Route file created: routes/{$filename}");
    }
    
    /**
     * Get routes template
     * 
     * @param string $name
     * @return string
     */
    protected function getTemplate(string $name): string
    {
        $title = ucwords(str_replace(['-', '_'], ' ', basename($name, '.php')));
        
        return <<<PHP
<?php

/**
 * {$title} Routes
 */

use Framework\Core\Http\Request;
use Framework\Core\Http\Response;
use Framework\Core\Routing\Router;

Router::group(['prefix' => '/{$name}'], function() {
    
    Router::get('/', function(Request \$request, Response \$response) {
        return \$response->json(['message' => 'Welcome to {$title} routes']);
    });

});

PHP;
    }
}
