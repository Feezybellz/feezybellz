<?php

namespace Framework\Core\Console\Commands;

use Framework\Core\Console\Command;

class MakeModelCommand extends Command
{
    public function execute(): void
    {
        $name = $this->argument(0);
        
        if (!$name) {
            $this->error('Model name is required.');
            $this->info('Usage: php console make:model <name>');
            return;
        }
        
        $modelPath = dirname(dirname(dirname(__DIR__))) . '/app/Models';
        
        if (!is_dir($modelPath)) {
            mkdir($modelPath, 0755, true);
        }
        
        $filePath = "{$modelPath}/{$name}.php";
        
        if (file_exists($filePath)) {
            $this->error("Model already exists: {$name}");
            return;
        }
        
        $template = $this->getTemplate($name);
        
        file_put_contents($filePath, $template);
        
        $this->success("Model created: {$name}");
    }
    
    /**
     * Get model template
     * 
     * @param string $className
     * @return string
     */
    protected function getTemplate(string $className): string
    {
        $table = strtolower($className) . 's';
        
        return <<<PHP
<?php

namespace App\Models;

use Framework\Core\Database\Model;

class {$className} extends Model
{
    /**
     * The table associated with the model
     * 
     * @var string
     */
    protected string \$table = '{$table}';
    
    /**
     * The primary key for the model
     * 
     * @var string
     */
    protected string \$primaryKey = 'id';
    
    /**
     * The attributes that are mass assignable
     * 
     * @var array
     */
    protected array \$fillable = [];
    
    /**
     * The attributes that should be hidden
     * 
     * @var array
     */
    protected array \$hidden = [];
}

PHP;
    }
}
