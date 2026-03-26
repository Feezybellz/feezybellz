<?php

namespace Framework\Core\Console\Commands;

use Framework\Core\Console\Command;

class HelpCommand extends Command
{
    public function execute(): void
    {
        echo "\n";
        echo "Framework Console - Help\n";
        echo "========================\n\n";
        
        echo "Available Commands:\n\n";
        
        $commands = [
            'setup'                 => 'Initialize framework directories and basic configuration',
            'make:migration <name>' => 'Create a new migration file',
            'make:controller <name>' => 'Create a new controller',
            'make:model <name>' => 'Create a new model',
            'make:seeder <name>' => 'Create a new seeder class',
            'make:env'              => 'Create a new .env configuration file',
            'migrate'               => 'Run database migrations',
            'migrate:rollback'      => 'Rollback the last N migrations [--step=N]',
            'db:seed'               => 'Run or list database seeders [name] [--list]',
            'serve'                 => 'Start the HTTP server [--host=HOST] [--port=PORT]',
            'websocket:serve'       => 'Start the WebSocket server [--host=HOST] [--port=PORT]',
            'queue:work'            => 'Start the Queue worker server',
            'queue:dashboard'       => 'Start the Queue monitoring dashboard',
            'schedule:run'          => 'Run scheduled background tasks (Cron) [--id=ID] [--name=NAME]',
            'test'                  => 'Run all application tests recursively',
            'help'                  => 'Display this help message',
        ];
        
        foreach ($commands as $command => $description) {
            printf("  %-30s %s\n", $command, $description);
        }
        
        echo "\n";
        echo "Examples:\n";
        echo "  php console make:migration create_users_table\n";
        echo "  php console make:controller UserController\n";
        echo "  php console make:model User\n";
        echo "  php console make:seeder ProductSeeder\n";
        echo "  php console make:env                    # Create with default template\n";
        echo "  php console migrate\n";
        echo "  php console migrate:rollback --step=2\n";
        echo "  php console db:seed ACLSeeder           # Run a specific seeder\n";
        echo "  php console serve --host=0.0.0.0 --port=8080\n";
        echo "  php console websocket:serve --port=9000 # Run websocket on port 9000\n";
        echo "  php console schedule:run                # Add this to your Linux crontab\n";
        echo "  php console schedule:run --name=test-logging-task                # Run a specific task\n";
        echo "\n";
    }
}