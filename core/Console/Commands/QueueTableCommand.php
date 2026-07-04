<?php

namespace Framework\Core\Console\Commands;

use Framework\Core\Console\Command;

class QueueTableCommand extends Command
{
    protected string $signature = 'queue:table';
    protected string $description = 'Create a migration for the queue jobs database table';

    public function execute(): void
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
            // Unix timestamps — the worker compares these with time()
            \$table->integer('reserved_at')->nullable();
            \$table->integer('available_at')->nullable();
            \$table->timestamps();
        });

        // Dead-letter store: jobs that exhausted their retries land here
        // (inspect with `php console queue:failed`, re-run with queue:retry).
        \$this->createTable('_framework_failed_jobs', function (Schema \$table) {
            \$table->id();
            \$table->string('queue');
            \$table->text('payload');
            \$table->text('error');
            \$table->string('failed_at');
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
        \$this->dropTable('_framework_failed_jobs');
    }
}

PHP;
    }
}
