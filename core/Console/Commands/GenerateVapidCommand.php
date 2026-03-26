<?php

namespace Framework\Core\Console\Commands;

use Framework\Core\Console\Command;
use Framework\Core\Push\Vapid;
use Exception;

class GenerateVapidCommand extends Command
{
    /**
     * Execute the command
     */
    public function execute(): void
    {
        $this->info("Generating VAPID Keys for Web Push...");

        try {
            // Generate the keys using our Vapid class
            $keys = Vapid::generateKeys();
            
            $this->success("Success! Add these to your .env file:");
            $this->line("--------------------------------------------------");
            $this->line("VAPID_PUBLIC_KEY=" . $keys['publicKey']);
            $this->line("VAPID_PRIVATE_KEY=" . $keys['privateKey']);
            $this->line("--------------------------------------------------");
            
            $this->info("Note: The Public Key goes to your frontend (JS Service Worker).");
            $this->warn("The Private Key stays safely on this server.");

        } catch (Exception $e) {
            $this->error("Failed to generate keys: " . $e->getMessage());
        }
    }
}