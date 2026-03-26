<?php

namespace Framework\Core\Console\Commands;

use Framework\Core\Console\Command;

class MakeEnvCommand extends Command
{
    /**
     * Execute the command
     * 
     * @return void
     */
    public function execute(): void
    {
        $rootPath = dirname(dirname(dirname(__DIR__)));
        $envPath = $rootPath . '/.env';
        $examplePath = $rootPath . '/.env.example';
        
        // Check if .env already exists
        if (file_exists($envPath) && !$this->option('force')) {
            $this->error('.env file already exists!');
            $this->info('Use --force to overwrite the existing file.');
            return;
        }
        
        // If .env.example exists and --copy flag is used, copy from example
        if (file_exists($examplePath) && $this->option('copy')) {
            $content = file_get_contents($examplePath);
            file_put_contents($envPath, $content);
            $this->success('.env file created from .env.example');
            return;
        }
        
        // Interactive mode or default template
        if ($this->option('interactive') || $this->option('i')) {
            $this->createInteractive($envPath);
        } else {
            $this->createFromTemplate($envPath);
        }
    }
    
    /**
     * Create .env file interactively
     * 
     * @param string $envPath
     * @return void
     */
    protected function createInteractive(string $envPath): void
    {
        $this->info('Creating .env file interactively...');
        $this->info('Press Enter to use default values shown in [brackets]');
        echo "\n";
        
        $config = [];
        
        // Application settings
        $this->info('Application Settings:');
        $config['APP_NAME'] = $this->ask('Application name', 'MyApp');
        $config['APP_ENV'] = $this->ask('Environment (local/production)', 'local');
        $config['APP_DEBUG'] = $this->ask('Debug mode (true/false)', 'true');
        $config['APP_URL'] = $this->ask('Application URL', 'http://localhost:8000');
        echo "\n";
        
        // Database settings
        $this->info('Database Settings:');
        $config['DB_CONNECTION'] = $this->ask('Database connection (mysql/mongodb)', 'mysql');
        $config['DB_HOST'] = $this->ask('Database host', '127.0.0.1');
        $config['DB_PORT'] = $this->ask('Database port', $config['DB_CONNECTION'] === 'mongodb' ? '27017' : '3306');
        $config['DB_DATABASE'] = $this->ask('Database name', 'database');
        $config['DB_USERNAME'] = $this->ask('Database username', 'root');
        $config['DB_PASSWORD'] = $this->ask('Database password', '');
        echo "\n";
        
        // Session settings
        if ($this->confirm('Configure session settings?')) {
            echo "\n";
            $this->info('Session Settings:');
            $config['SESSION_DRIVER'] = $this->ask('Session driver (file/database/redis)', 'file');
            $config['SESSION_LIFETIME'] = $this->ask('Session lifetime (minutes)', '120');
            echo "\n";
        }
        
        // Cache settings
        if ($this->confirm('Configure cache settings?')) {
            echo "\n";
            $this->info('Cache Settings:');
            $config['CACHE_DRIVER'] = $this->ask('Cache driver (file/redis/memcached)', 'file');
            echo "\n";
        }
        
        // Build .env content
        $content = $this->buildEnvContent($config);
        file_put_contents($envPath, $content);
        
        $this->success('.env file created successfully!');
    }
    
    /**
     * Create .env file from template
     * 
     * @param string $envPath
     * @return void
     */
    protected function createFromTemplate(string $envPath): void
    {
        $template = <<<ENV
# Application Configuration
APP_NAME=MyApp
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database Configuration
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=database
DB_USERNAME=root
DB_PASSWORD=

# MongoDB Configuration (if using MongoDB)
MONGO_HOST=127.0.0.1
MONGO_PORT=27017
MONGO_DATABASE=database
MONGO_USERNAME=
MONGO_PASSWORD=

# Session Configuration
SESSION_DRIVER=file
SESSION_LIFETIME=120

# Cache Configuration
CACHE_DRIVER=file

# Mail Configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@example.com
MAIL_FROM_NAME=\${APP_NAME}

# Security
APP_KEY=
CSRF_TOKEN_NAME=csrf_token
CSRF_TOKEN_LIFETIME=3600

# File Upload
MAX_UPLOAD_SIZE=10485760
ALLOWED_UPLOAD_TYPES=jpg,jpeg,png,gif,pdf,doc,docx

# Logging
LOG_LEVEL=debug
LOG_CHANNEL=daily

ENV;

        file_put_contents($envPath, $template);
        $this->success('.env file created with default template');
        $this->info('Edit the .env file to configure your application settings.');
    }
    
    /**
     * Build .env content from config array
     * 
     * @param array $config
     * @return string
     */
    protected function buildEnvContent(array $config): string
    {
        $lines = [
            '# Application Configuration',
            "APP_NAME={$config['APP_NAME']}",
            "APP_ENV={$config['APP_ENV']}",
            "APP_DEBUG={$config['APP_DEBUG']}",
            "APP_URL={$config['APP_URL']}",
            '',
            '# Database Configuration',
            "DB_CONNECTION={$config['DB_CONNECTION']}",
            "DB_HOST={$config['DB_HOST']}",
            "DB_PORT={$config['DB_PORT']}",
            "DB_DATABASE={$config['DB_DATABASE']}",
            "DB_USERNAME={$config['DB_USERNAME']}",
            "DB_PASSWORD={$config['DB_PASSWORD']}",
            '',
        ];
        
        if (isset($config['SESSION_DRIVER'])) {
            $lines[] = '# Session Configuration';
            $lines[] = "SESSION_DRIVER={$config['SESSION_DRIVER']}";
            $lines[] = "SESSION_LIFETIME={$config['SESSION_LIFETIME']}";
            $lines[] = '';
        }
        
        if (isset($config['CACHE_DRIVER'])) {
            $lines[] = '# Cache Configuration';
            $lines[] = "CACHE_DRIVER={$config['CACHE_DRIVER']}";
            $lines[] = '';
        }
        
        $lines[] = '# Security';
        $lines[] = 'APP_KEY=';
        $lines[] = 'CSRF_TOKEN_NAME=csrf_token';
        $lines[] = 'CSRF_TOKEN_LIFETIME=3600';
        $lines[] = '';
        
        return implode("\n", $lines);
    }
    
    /**
     * Ask user for input
     * 
     * @param string $question
     * @param string|null $default
     * @return string
     */
    protected function ask(string $question, ?string $default = null): string
    {
        if ($default !== null) {
            echo "  {$question} [{$default}]: ";
        } else {
            echo "  {$question}: ";
        }
        
        $answer = trim(fgets(STDIN));
        
        return $answer !== '' ? $answer : ($default ?? '');
    }
    
    /**
     * Ask user for confirmation
     * 
     * @param string $question
     * @return bool
     */
    protected function confirm(string $question): bool
    {
        echo "  {$question} (yes/no) [no]: ";
        $answer = trim(strtolower(fgets(STDIN)));
        
        return in_array($answer, ['y', 'yes']);
    }
}
