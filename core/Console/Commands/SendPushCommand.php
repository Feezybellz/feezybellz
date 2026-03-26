<?php

namespace Framework\Core\Console\Commands;

use Framework\Core\Console\Command;
use Framework\Core\Push\Push;
use Exception;

class SendPushCommand extends Command
{
    /**
     * Execute the command
     */
    public function execute(): void
    {
        // Require the token as the first argument
        $token = $this->argument(0);

        if (!$token) {
            $this->error("Missing token! Usage: php console push:send <token> [--title='...'] [--body='...']");
            return;
        }

        // Grab options or fall back to defaults
        $title = $this->option('title', 'System Test');
        $body  = $this->option('body', 'This is a test notification from the CLI.');

        $this->info("Sending push notification to token: " . substr($token, 0, 15) . "...");

        try {
            // Use our beautiful Fluent Push Builder
            Push::to($token)
                ->title($title)
                ->body($body)
                ->send();

            $this->success("Push notification dispatched successfully!");

        } catch (Exception $e) {
            $this->error("Push failed: " . $e->getMessage());
        }
    }
}