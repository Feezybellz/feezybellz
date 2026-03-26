<?php

namespace Framework\Core\Console\Commands;

use Framework\Core\Console\Command;

class MakeControllerCommand extends Command
{
    public function execute(): void
    {
        $name = $this->argument(0);
        
        if (!$name) {
            $this->error('Controller name is required.');
            $this->info('Usage: php console make:controller <name>');
            return;
        }
        
        // Ensure name ends with 'Controller'
        if (!str_ends_with($name, 'Controller')) {
            $name .= 'Controller';
        }
        
        $controllerPath = dirname(dirname(dirname(__DIR__))) . '/app/Controllers';
        
        if (!is_dir($controllerPath)) {
            mkdir($controllerPath, 0755, true);
        }
        
        $filePath = "{$controllerPath}/{$name}.php";
        
        if (file_exists($filePath)) {
            $this->error("Controller already exists: {$name}");
            return;
        }
        
        $template = $this->getTemplate($name);
        
        file_put_contents($filePath, $template);
        
        $this->success("Controller created: {$name}");
    }
    
    /**
     * Get controller template
     * 
     * @param string $className
     * @return string
     */
    protected function getTemplate(string $className): string
    {
        return <<<PHP
<?php

namespace App\Controllers;

use Framework\Core\Http\Request;
use Framework\Core\Http\Response;

class {$className}
{
    /**
     * Display a listing of the resource
     * 
     * @param Request \$request
     * @param Response \$response
     * @param array \$params
     * @return mixed
     */
    public function index(Request \$request, Response \$response, array \$params)
    {
        // Your code here
        return \$response->json(['message' => 'Hello from {$className}']);
    }
    
    /**
     * Show a specific resource
     * 
     * @param Request \$request
     * @param Response \$response
     * @param array \$params
     * @return mixed
     */
    public function show(Request \$request, Response \$response, array \$params)
    {
        // Your code here
        return \$response->json(['id' => \$params['id'] ?? null]);
    }
    
    /**
     * Create a new resource
     * 
     * @param Request \$request
     * @param Response \$response
     * @param array \$params
     * @return mixed
     */
    public function store(Request \$request, Response \$response, array \$params)
    {
        // Your code here
        return \$response->json(['message' => 'Resource created'], 201);
    }
    
    /**
     * Update a resource
     * 
     * @param Request \$request
     * @param Response \$response
     * @param array \$params
     * @return mixed
     */
    public function update(Request \$request, Response \$response, array \$params)
    {
        // Your code here
        return \$response->json(['message' => 'Resource updated']);
    }
    
    /**
     * Delete a resource
     * 
     * @param Request \$request
     * @param Response \$response
     * @param array \$params
     * @return mixed
     */
    public function destroy(Request \$request, Response \$response, array \$params)
    {
        // Your code here
        return \$response->json(['message' => 'Resource deleted']);
    }
}

PHP;
    }
}
