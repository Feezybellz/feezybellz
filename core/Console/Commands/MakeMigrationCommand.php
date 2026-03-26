<?php

namespace Framework\Core\Console\Commands;

use Framework\Core\Console\Command;

class MakeMigrationCommand extends Command
{
    public function execute(): void
    {
        $name = $this->argument(0);
        
        if (!$name) {
            $this->error('Migration name is required.');
            $this->info('Usage: php console make:migration <name>');
            return;
        }
        
        $timestamp = date('YmdHis');
        $filename = "{$timestamp}_{$name}.php";
        $className = $this->toCamelCase($name);
        
        $migrationPath = dirname(dirname(dirname(__DIR__))) . '/database/migrations';
        
        if (!is_dir($migrationPath)) {
            mkdir($migrationPath, 0755, true);
        }
        
        $filePath = "{$migrationPath}/{$filename}";
        
        $template = $this->getTemplate($className);
        
        file_put_contents($filePath, $template);
        
        $this->success("Migration created: {$filename}");
    }
    
    /**
     * Convert snake_case to CamelCase
     * 
     * @param string $string
     * @return string
     */
    protected function toCamelCase(string $string): string
    {
        return str_replace('_', '', ucwords($string, '_'));
    }
    
    /**
     * Get migration template
     * 
     * @param string $className
     * @return string
     */
    protected function getTemplate(string $className): string
    {
        \$tableName = strtolower(preg_replace('/(?<!^)[A-Z]/', '_\$0', \$className));
        if (str_starts_with(\$tableName, 'create_')) {
            \$tableName = substr(\$tableName, 7);
        }
        if (str_ends_with(\$tableName, '_table')) {
            \$tableName = substr(\$tableName, 0, -6);
        }

        return <<<PHP
<?php

use Framework\Core\Database\Migration;
use Framework\Core\Database\Schema;

class {$className} extends Migration
{
    /**
     * Run the migration
     * 
     * @return void
     */
    public function up(): void
    {
        \$this->createTable('{\$tableName}', function (Schema \$table) {
            \$table->id();
            // \$table->string('name');
            \$table->timestamps();
        });
    }
    
    /**
     * Reverse the migration
     * 
     * @return void
     */
    public function down(): void
    {
        \$this->dropTable('{\$tableName}');
    }
}

PHP;
    }
}
