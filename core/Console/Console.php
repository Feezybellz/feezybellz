<?php

namespace Framework\Core\Console;

class Console
{
    protected $argv;
    protected $commands = [];
    
    public function __construct(array $argv)
    {
        $this->argv = $argv;
        $this->registerCommands();
        $this->loadCustomCommands();
    }

    /**
     * Extend the console with custom commands
     */
    public function extend(array $commands): void
    {
        $this->commands = array_merge($this->commands, $commands);
    }

    /**
     * Load commands from app/Console/Kernel.php if it exists
     */
    protected function loadCustomCommands(): void
    {
        $kernelPath = dirname(dirname(__DIR__)) . '/app/Console/Kernel.php';
        if (file_exists($kernelPath)) {
            $commands = require $kernelPath;
            if (is_array($commands)) {
                $this->extend($commands);
            }
        }
    }
    
    /**
     * Register all available commands
     * * @return void
     */
    protected function registerCommands(): void
    {
        $this->commands = [
            'make:migration'   => Commands\MakeMigrationCommand::class,
            'make:controller'  => Commands\MakeControllerCommand::class,
            'make:model'       => Commands\MakeModelCommand::class,
            'make:middleware'  => Commands\MakeMiddlewareCommand::class,
            'make:routes'      => Commands\MakeRoutesCommand::class,
            'make:job'         => Commands\MakeJobCommand::class,
            'make:event'       => Commands\MakeEventCommand::class,
            'make:listener'    => Commands\MakeListenerCommand::class,
            'make:class'       => Commands\MakeClassCommand::class,
            'make:seeder'      => Commands\MakeSeederCommand::class,
            'make:service'     => Commands\MakeServiceCommand::class,
            'make:env'         => Commands\MakeEnvCommand::class,
            'migrate'          => Commands\MigrateCommand::class,
            'migrate:rollback' => Commands\MigrateRollbackCommand::class,
            'db:seed'          => Commands\SeedCommand::class,
            'route:cache'      => Commands\RouteCacheCommand::class,
            'route:uncache'    => Commands\RouteClearCommand::class,
            'route:clear'      => Commands\RouteClearCommand::class, // alias of route:uncache
            'serve'            => Commands\ServeCommand::class,
            'websocket:serve'  => Commands\WebSocketServeCommand::class,
            
            // ── Queue Commands ──────────────────────────────────────────
            'queue:serve'      => Commands\QueueServeCommand::class,
            'queue:ui'         => Commands\QueueDashboardCommand::class,
            'queue:test'       => Commands\QueueTestCommand::class,
            
            'help'             => Commands\HelpCommand::class,
            'schedule:run'     => Commands\ScheduleRunCommand::class,
            'setup'            => Commands\SetupCommand::class,
            'system:permissions' => Commands\SystemPermissionsCommand::class,
            'test'             => Commands\TestRunCommand::class,

            'push:generate'    => Commands\GenerateVapidCommand::class,
            'push:vapid'       => Commands\GenerateVapidCommand::class,
            'push:send'        => Commands\SendPushCommand::class,

            'jwt:generate'     => Commands\GenerateJwtSecretCommand::class,

            'event'            => Commands\DispatchEventCommand::class,
        ];
    }
    
    /**
     * Run the console application
     * * @return void
     */
    public function run(): void
    {
        if (count($this->argv) < 2) {
            $this->showHelp();
            return;
        }
        
        $commandName = $this->argv[1];
        
        if (!isset($this->commands[$commandName])) {
            $this->error("Command '{$commandName}' not found.");
            $this->showHelp();
            return;
        }
        
        $commandClass = $this->commands[$commandName];
        $command = new $commandClass($this->argv);
        $command->execute();
    }
    
    /**
     * Show help information
     * * @return void
     */
    protected function showHelp(): void
    {
        echo "\n";
        echo "Framework Console\n";
        echo "=================\n\n";
        echo "Available Commands:\n";
        
        foreach ($this->commands as $name => $class) {
            echo "  php console {$name}\n";
        }
        
        echo "\n";
    }
    
    /**
     * Display an error message
     * * @param string $message
     * @return void
     */
    protected function error(string $message): void
    {
        echo "\033[31mError: {$message}\033[0m\n";
    }
}
