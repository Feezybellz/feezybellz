<?php

namespace Framework\Core\Console\Commands;

use Framework\Core\Console\Command;

/**
 * `php console route:uncache`  (also aliased as `route:clear`)
 *
 * Deletes bootstrap/cache/routes.php so the next request falls back to the
 * fresh loadRoutesFrom() path. Safe to run at any time — idempotent when
 * no cache file is present.
 */
class RouteClearCommand extends Command
{
    public function execute(): void
    {
        $cacheFile = dirname(dirname(dirname(__DIR__))) . '/bootstrap/cache/routes.php';
        if (!file_exists($cacheFile)) {
            $this->info("No route cache to clear.");
            return;
        }
        if (@unlink($cacheFile)) {
            $this->success("Route cache cleared.");
        } else {
            $this->error("Failed to delete {$cacheFile}.");
        }
    }
}
