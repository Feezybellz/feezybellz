<?php

namespace Framework\Core\Console\Commands;

use Framework\Core\Console\Command;
use Exception;

class GenerateJwtSecretCommand extends Command
{
    /**
     * Execute the command
     */
    public function execute(): void
    {
        $this->info("Generating JWT Secret...");

        try {
            // Generate a secure 64-character random string
            $secret = bin2hex(random_bytes(32));
            
            $this->success("Success! Add this to your .env file:");
            $this->line("--------------------------------------------------");
            $this->line("JWT_SECRET=" . $secret);
            $this->line("--------------------------------------------------");
            
            $this->warn("Make sure to keep this secret safe and never share it.");

            if ($this->option('write')) {
                $this->writeToEnv($secret);
            }

        } catch (Exception $e) {
            $this->error("Failed to generate secret: " . $e->getMessage());
        }
    }

    /**
     * Write the secret to the .env file
     */
    protected function writeToEnv(string $secret): void
    {
        $envFile = dirname(dirname(dirname(dirname(__DIR__)))) . '/.env';
        
        if (!file_exists($envFile)) {
            $this->warn(".env file not found. Creating a new one...");
            file_put_contents($envFile, "JWT_SECRET=" . $secret . PHP_EOL);
            $this->success("Created .env with JWT_SECRET.");
            return;
        }

        $content = file_get_contents($envFile);
        
        if (strpos($content, 'JWT_SECRET=') !== false) {
            $content = preg_replace('/JWT_SECRET=.*/', 'JWT_SECRET=' . $secret, $content);
            $this->info("Updated existing JWT_SECRET in .env.");
        } else {
            $content .= PHP_EOL . "JWT_SECRET=" . $secret . PHP_EOL;
            $this->info("Added JWT_SECRET to .env.");
        }

        file_put_contents($envFile, $content);
        $this->success("Successfully updated .env file.");
    }
}