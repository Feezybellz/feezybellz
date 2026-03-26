<?php

namespace Framework\Core\Console\Commands;

use Framework\Core\Console\Command;

class MakeSeederCommand extends Command
{
    /**
     * Execute the make:seeder command
     * 
     * @return void
     */
    public function execute(): void
    {
        $name = $this->argument(0);

        if (!$name) {
            $this->error('Seeder name is required.');
            $this->info('Usage: php console make:seeder <name>');
            return;
        }

        // Ensure name ends with "Seeder" for consistency
        if (!str_ends_with($name, 'Seeder')) {
            $name .= 'Seeder';
        }

        $seedersPath = dirname(dirname(dirname(__DIR__))) . '/database/seeders';

        if (!is_dir($seedersPath)) {
            mkdir($seedersPath, 0755, true);
        }

        $filePath = "{$seedersPath}/{$name}.php";

        if (file_exists($filePath)) {
            $this->error("Seeder already exists: {$name}");
            return;
        }

        $template = $this->getTemplate($name);

        file_put_contents($filePath, $template);

        $this->success("Seeder created: database/seeders/{$name}.php");
    }

    /**
     * Get the seeder class template
     * 
     * @param string $className
     * @return string
     */
    protected function getTemplate(string $className): string
    {
        return <<<PHP
<?php

use Framework\Core\Database\Seeder;

class {$className} extends Seeder
{
    /**
     * Run the seeder
     * 
     * @return void
     */
    public function run(): void
    {
        // Define your seeding logic here
    }
}

PHP;
    }
}
