<?php

namespace Framework\Core\Console\Commands;

use Framework\Core\Console\Command;

class MakeListenerCommand extends Command
{
    public function execute(): void
    {
        $name = $this->argument(0);
        
        if (!$name) {
            $this->error('Listener name is required.');
            $this->info('Usage: php console make:listener <name>');
            return;
        }
        
        $listenerPath = dirname(dirname(dirname(__DIR__))) . '/app/Listeners';
        
        if (!is_dir($listenerPath)) {
            mkdir($listenerPath, 0755, true);
        }
        
        $filePath = "{$listenerPath}/{$name}.php";
        
        if (file_exists($filePath)) {
            $this->error("Listener already exists: {$name}");
            return;
        }
        
        $template = $this->getTemplate($name);
        
        file_put_contents($filePath, $template);
        
        $this->success("Listener created: {$name}");
    }
    
    /**
     * Get listener template
     * 
     * @param string $className
     * @return string
     */
    protected function getTemplate(string $className): string
    {
        return <<<PHP
<?php

namespace App\Listeners;

use Framework\Core\Events\ListenerInterface;
use Framework\Core\Events\Event;

class {$className} implements ListenerInterface
{
    /**
     * Handle the event
     * 
     * @param Event \$event
     * @return void
     */
    public function handle(Event \$event): void
    {
        // Your listener logic here
    }
}

PHP;
    }
}
