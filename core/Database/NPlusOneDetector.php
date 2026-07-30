<?php

namespace Framework\Core\Database;

/**
 * N+1 query detector.
 *
 * Registers itself as a DB::listen() callback and tracks how often each
 * *shape* of query fires within one request. When the same shape (a
 * normalized SQL string with all bound placeholders masked) repeats more
 * than the configured threshold, it's very likely an eager-load miss —
 * the classic "load users, then load each user's posts one at a time" bug.
 *
 * On detection the detector either:
 *   - logs a warning via Log::warning() (default),
 *   - triggers an E_USER_WARNING (loud in dev, useful for tests),
 *   - or throws (opt-in via `throw_on_detect: true`).
 *
 * Register in a service provider or bootstrap file:
 *
 *   \Framework\Core\Database\NPlusOneDetector::install([
 *       'threshold' => 5,        // more than this many repeats = N+1
 *       'throw'     => false,     // dev-mode: set true to fail loud
 *   ]);
 *
 * Auto-enabled when APP_DEBUG=true by wiring in Kernel; the class itself
 * is silent unless install() is called.
 */
class NPlusOneDetector
{
    private static ?self $instance = null;

    /** @var array<string, int> Normalized SQL → repetition count */
    private array $counts = [];

    private int $threshold;
    private bool $throwOnDetect;

    private function __construct(int $threshold, bool $throw)
    {
        $this->threshold = $threshold;
        $this->throwOnDetect = $throw;
    }

    /**
     * Install the detector as a DB::listen() callback. Idempotent — calling
     * install() twice replaces the earlier registration.
     *
     * @param array{threshold?: int, throw?: bool} $options
     */
    public static function install(array $options = []): self
    {
        $threshold = (int) ($options['threshold'] ?? 5);
        $throw     = (bool) ($options['throw'] ?? false);

        self::$instance = new self($threshold, $throw);

        DB::listen(function (string $sql, array $params) {
            if (self::$instance !== null) { self::$instance->recordQuery($sql); }
        });

        return self::$instance;
    }

    /**
     * Reset the per-request counter. Call in State::resetPerRequest() or
     * between test cases.
     */
    public static function reset(): void
    {
        if (self::$instance !== null) {
            self::$instance->counts = [];
        }
    }

    /**
     * Return the current per-shape counts (mostly for tests / diagnostics).
     * @return array<string, int>
     */
    public static function snapshot(): array
    {
        return self::$instance ? self::$instance->counts : [];
    }

    /**
     * Called by the DB::listen() hook on every query.
     */
    private function recordQuery(string $sql): void
    {
        // Normalize: strip parameters (?) and integer-literals so
        // "WHERE id = 1" and "WHERE id = 2" collapse to the same shape.
        // Preserve identifiers so different tables don't collide.
        $normalized = preg_replace('/\?|\b\d+\b/', '?', trim($sql));
        $normalized = preg_replace('/\s+/', ' ', (string) $normalized);
        // Only SELECT queries — N+1 is a read-pattern bug. Ignore inserts
        // and updates.
        if (stripos($normalized, 'SELECT') !== 0) {
            return;
        }

        $this->counts[$normalized] = ($this->counts[$normalized] ?? 0) + 1;
        $count = $this->counts[$normalized];

        if ($count === $this->threshold + 1) {
            // First time we crossed the line — announce it exactly once.
            $this->announce($normalized, $count);
        }
    }

    private function announce(string $sql, int $count): void
    {
        $preview = strlen($sql) > 200 ? substr($sql, 0, 200) . '…' : $sql;
        $message = "N+1 suspected: this SQL shape has repeated {$count} times in one request. Query: {$preview}";

        if ($this->throwOnDetect) {
            throw new \RuntimeException($message);
        }

        if (class_exists(\Framework\Core\Logging\Log::class)) {
            \Framework\Core\Logging\Log::warning($message, ['sql' => $sql, 'count' => $count]);
        } else {
            trigger_error($message, E_USER_WARNING);
        }
    }
}
