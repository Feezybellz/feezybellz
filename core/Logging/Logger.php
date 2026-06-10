<?php

namespace Framework\Core\Logging;

class Logger
{
    protected $logPath;
    protected $minLevel;

    /**
     * PSR-3 Log Levels with numeric weights
     */
    protected const LEVELS = [
        'emergency' => 0,
        'alert'     => 1,
        'critical'  => 2,
        'error'     => 3,
        'warning'   => 4,
        'notice'    => 5,
        'info'      => 6,
        'debug'     => 7,
    ];

    public function __construct(string $logPath = null, string $minLevel = 'info')
    {
        // Default to a daily rotating log file in the storage/logs directory
        if ($logPath === null) {
            $dir = function_exists('storage_path') ? storage_path('logs') : (__DIR__ . '/../../storage/logs');
            $this->logPath = $dir . '/app-' . date('Y-m-d') . '.log';
        } else {
            $this->logPath = $logPath;
        }

        $this->minLevel = strtolower($minLevel);
        $this->ensureLogDirectoryExists();
    }

    /**
     * Create the log directory if it does not exist yet.
     */
    protected function ensureLogDirectoryExists(): void
    {
        $dir = dirname($this->logPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
    }

    /**
     * The core logging method.
     */
    public function log(string $level, string $message, array $context = []): void
    {
        $level = strtolower($level);

        // Filter by log level
        if (!$this->shouldLog($level)) {
            return;
        }

        $date = date('Y-m-d H:i:s');
        
        // Convert the context array to a JSON string for easy reading, if provided
        $contextString = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';
        
        $levelUpper = strtoupper($level);
        
        // Format: [2026-02-23 14:30:00] ERROR: Something broke {"user_id": 5}
        $logEntry = "[{$date}] {$levelUpper}: {$message}{$contextString}" . PHP_EOL;
        
        // Write to the file, appending to the end, and locking the file during write to prevent concurrent corruption
        @file_put_contents($this->logPath, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Check if the message level meets the minimum log level threshold.
     */
    protected function shouldLog(string $level): bool
    {
        $currentWeight = self::LEVELS[$level] ?? 7;
        $minWeight = self::LEVELS[$this->minLevel] ?? 6;

        return $currentWeight <= $minWeight;
    }

    // ==========================================
    // PSR-3 Convenience Methods
    // ==========================================

    public function emergency(string $message, array $context = []): void { $this->log('emergency', $message, $context); }
    public function alert(string $message, array $context = []): void { $this->log('alert', $message, $context); }
    public function critical(string $message, array $context = []): void { $this->log('critical', $message, $context); }
    public function error(string $message, array $context = []): void { $this->log('error', $message, $context); }
    public function warning(string $message, array $context = []): void { $this->log('warning', $message, $context); }
    public function notice(string $message, array $context = []): void { $this->log('notice', $message, $context); }
    public function info(string $message, array $context = []): void { $this->log('info', $message, $context); }
    public function debug(string $message, array $context = []): void { $this->log('debug', $message, $context); }
}
