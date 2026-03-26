<?php

namespace Framework\Core\Console\Commands;

use Framework\Core\Console\Command;

class MakeEventCommand extends Command
{
    public function execute(): void
    {
        $name = $this->argument(0);
        
        if (!$name) {
            $this->error('Event name is required.');
            $this->info('Usage: php console make:event <name>');
            return;
        }
        
        $eventPath = dirname(dirname(dirname(__DIR__))) . '/app/Events';
        
        if (!is_dir($eventPath)) {
            mkdir($eventPath, 0755, true);
        }
        
        $filePath = "{$eventPath}/{$name}.php";
        
        if (file_exists($filePath)) {
            $this->error("Event already exists: {$name}");
            return;
        }
        
        $template = $this->getTemplate($name);
        
        file_put_contents($filePath, $template);
        
        $this->success("Event created: {$name}");
    }
    
    /**
     * Get event template
     * 
     * @param string $className
     * @return string
     */
    protected function getTemplate(string $className): string
    {
        return <<<PHP
<?php

namespace App\Events;

use Framework\Core\Events\Event;

class {$className} extends Event
{
    /**
     * Create a new event instance
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
