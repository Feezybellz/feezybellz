<?php

namespace Framework\Core\Console\Commands;

use Framework\Core\Console\Command;

class QueueTableCommand extends Command
{
    protected static $defaultName = 'queue:table';
    protected static $description = 'Create a migration for the queue jobs database table';

    public function execute(array $args): void
    {
        $timestamp = date('YmdHis');
        $filename = "{$timestamp}_create_framework_jobs_table.php";
        $className = "CreateFrameworkJobsTable";
        
        $migrationPath = dirname(dirname(dirname(__DIR__))) . '/database/migrations';
        
        if (!is_dir($migrationPath)) {
            mkdir($migrationPath, 0755, true);
        }
        
        $filePath = "{$migrationPath}/{$filename}";
        
        $template = $this->getTemplate($className);
        
        file_put_contents($filePath, $template);
        
        $this->success("Migration created successfully: {$filename}");
    }
    
    protected function getTemplate(string $className): string
    {
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
        \$this->createTable('_framework_jobs', function (Schema \$table) {
            \$table->id();
            \$table->string('queue');
            \$table->text('payload');
            \$table->integer('attempts');
            // Using integer for timestamps to match our database worker needs
            \$table->integer('reserved_at')->nullable();
            \$table->integer('available_at')->nullable();
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
        \$this->dropTable('_framework_jobs');
    }
}

PHP;
    }
}
