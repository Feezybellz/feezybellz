<?php

/**
 * Logging configuration.
 *
 * Central place for log-related knobs. Log::getLogger() reads config('logging.*')
 * so tests can swap the level/channel without hot-patching the class.
 */

return [

    /*
    | Minimum level a message must have to be emitted.
    | One of: debug | info | notice | warning | error | critical | alert | emergency.
    */
    'level' => env('LOG_LEVEL', 'info'),

    /*
    | Default channel name — appears in the log line so multi-tenant or
    | multi-service deployments can differentiate their sources.
    */
    'channel' => env('LOG_CHANNEL', 'default'),

    /*
    | Where log files land. Empty = the framework's default
    | (storage/logs/framework-YYYY-MM-DD.log). Absolute or project-relative paths
    | accepted.
    */
    'path' => env('LOG_PATH', ''),

    /*
    | How many daily rotated files to keep before oldest are pruned.
    */
    'daily_max_files' => (int) env('LOG_DAILY_MAX_FILES', 14),

];
