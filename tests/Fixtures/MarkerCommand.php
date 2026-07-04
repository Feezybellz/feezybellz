<?php

namespace Tests\Fixtures;

use Framework\Core\Console\Command;

/**
 * A Command fixture that records that it ran and which arguments it was
 * constructed with. Reset the statics in setUp().
 */
class MarkerCommand extends Command
{
    public static bool $ran = false;
    public static array $seenArguments = [];

    public function execute(): void
    {
        self::$ran = true;
        self::$seenArguments = $this->arguments;
    }
}
