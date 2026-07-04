<?php

namespace Tests\Fixtures;

/**
 * A listener fixture that records every event it handles on a static
 * array so tests can assert against it. Reset $handled in setUp().
 */
class RecordingListener
{
    /** @var array<int, object> */
    public static array $handled = [];

    public function handle(object $event): void
    {
        self::$handled[] = $event;
    }
}
