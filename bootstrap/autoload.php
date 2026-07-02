<?php

/**
 * Native PSR-4 Autoloader
 * Maps Framework\Core\ to /core/ and App\ to /app/
 */
spl_autoload_register(function ($class) {
    $basePath = dirname(__DIR__);
    
    // Namespace mappings
    $prefixes = [
        'Framework\\Core\\' => $basePath . '/core/',
        'App\\' => $basePath . '/app/',
    ];
    
    foreach ($prefixes as $prefix => $directory) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }
        
        // Get the relative class name
        $relativeClass = substr($class, $len);
        
        // Replace namespace separators with directory separators
        $file = $directory . str_replace('\\', '/', $relativeClass) . '.php';
        
        // If the file exists, require it
        if (file_exists($file)) {
            require $file;
            return;
        }
    }
});

// Load Composer's autoloader if it exists (for user dependencies)
$composerAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require $composerAutoload;
}

// Load global helpers
require __DIR__ . '/helpers.php';
env();

// Set application timezone
date_default_timezone_set(config('app.timezone', 'UTC'));

// Database connection *configs* are registered here (cheap) but the drivers
// are NOT built until DB::connection() is first called. This matters for
// CLI commands like `make:*` that never touch the database — they no longer
// pay the connection-setup cost. See DB::connection() for the lazy path.
use Framework\Core\Database\DB;
$dbConfig = config('db');
if ($dbConfig && isset($dbConfig['connections'])) {
    foreach ($dbConfig['connections'] as $name => $connection) {
        DB::addConnection($name, $connection);
    }
}
