<?php

namespace Framework\Core\Console\Commands;

use Framework\Core\Console\Command;

class MakeJobCommand extends Command
{
    public function execute(): void
    {
        $name = $this->argument(0);
        
        if (!$name) {
            $this->error('Job name is required.');
            $this->info('Usage: php console make:job <name>');
            return;
        }
        
        // Ensure name ends with 'Job'
        if (!str_ends_with($name, 'Job')) {
            $name .= 'Job';
        }
        
        $jobPath = dirname(dirname(dirname(__DIR__))) . '/app/Jobs';
        
        if (!is_dir($jobPath)) {
            mkdir($jobPath, 0755, true);
        }
        
        $filePath = "{$jobPath}/{$name}.php";
        
        if (file_exists($filePath)) {
            $this->error("Job already exists: {$name}");
            return;
        }
        
        $template = $this->getTemplate($name);
        
        file_put_contents($filePath, $template);
        
        $this->success("Job created: {$name}");
    }
    
    /**
     * Get job template
     * 
     * @param string $className
     * @return string
     */
    protected function getTemplate(string $className): string
    {
        return <<<PHP
<?php

namespace App\Jobs;

class {$className}
{
    /**
     * Create a new job instance
     * 
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job
     * 
     * @return void
     */
    public function handle(): void
    {
        // Your job logic here
    }
}

PHP;
    }
}
