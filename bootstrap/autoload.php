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

// Initialize database connections
use Framework\Core\Database\DB;

// Set application timezone
date_default_timezone_set(config('app.timezone', 'UTC'));

$dbConfig = config('db');
if ($dbConfig && isset($dbConfig['connections'])) {
    foreach ($dbConfig['connections'] as $name => $connection) {
        try {
            DB::addConnection($name, $connection);
        } catch (Exception $e) {
            // Silently fail if connection cannot be established
            // This allows the app to run even if DB is not configured
        }
    }
}
