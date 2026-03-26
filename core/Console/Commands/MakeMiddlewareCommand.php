<?php

namespace Framework\Core\Console\Commands;

use Framework\Core\Console\Command;

class MakeMiddlewareCommand extends Command
{
    public function execute(): void
    {
        $name = $this->argument(0);
        
        if (!$name) {
            $this->error('Middleware name is required.');
            $this->info('Usage: php console make:middleware <name>');
            return;
        }
        
        // Ensure name ends with 'Middleware'
        if (!str_ends_with($name, 'Middleware')) {
            $name .= 'Middleware';
        }
        
        $middlewarePath = dirname(dirname(dirname(__DIR__))) . '/app/Middleware';
        
        if (!is_dir($middlewarePath)) {
            mkdir($middlewarePath, 0755, true);
        }
        
        $filePath = "{$middlewarePath}/{$name}.php";
        
        if (file_exists($filePath)) {
            $this->error("Middleware already exists: {$name}");
            return;
        }
        
        $template = $this->getTemplate($name);
        
        file_put_contents($filePath, $template);
        
        $this->success("Middleware created: {$name}");
    }
    
    /**
     * Get middleware template
     * 
     * @param string $className
     * @return string
     */
    protected function getTemplate(string $className): string
    {
        return <<<PHP
<?php

namespace App\Middleware;

use Framework\Core\Http\Request;
use Framework\Core\Http\Response;

class {$className}
{
    /**
     * Handle the incoming request
     * 
     * @param Request \$request
     * @param Response \$response
     * @param callable \$next
     * @return mixed
     */
    public function handle(Request \$request, Response \$response, callable \$next)
    {
        // Your middleware logic here
        
        return \$next(\$request, \$response);
    }
}

PHP;
    }
}
