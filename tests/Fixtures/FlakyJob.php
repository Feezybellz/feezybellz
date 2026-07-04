<?php

namespace Tests\Fixtures;

/**
 * A job fixture that fails a configurable number of times before
 * succeeding — for exercising the retry/backoff/dead-letter lifecycle.
 * Reset the statics in setUp().
 */
class FlakyJob
{
    public static int $calls = 0;
    public static int $failures = 0; // how many initial calls should throw

    public function attempt(string $marker = ''): void
    {
        self::$calls++;
        if (self::$calls <= self::$failures) {
            throw new \RuntimeException("flaky failure #" . self::$calls . ($marker !== '' ? " ({$marker})" : ''));
        }
    }

    public static function staticAttempt(): void
    {
        self::$calls++;
        if (self::$calls <= self::$failures) {
            throw new \RuntimeException("flaky static failure #" . self::$calls);
        }
    }
}
