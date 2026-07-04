<?php

namespace Tests\Unit;

use Framework\Core\Console\Console;
use Framework\Core\Console\Command;
use Framework\Core\Testing\TestCase;

/**
 * Guards the console command registry (remaining.md §8.1.1 / §8.1.2).
 *
 * Catches two regression classes:
 *  - a registered command name mapping to a class that doesn't exist
 *    (`queue:test` used to point at a phantom QueueTestCommand), and
 *  - a command whose execute() signature is incompatible with the abstract
 *    Command::execute(): void (loading such a class is a PHP fatal, so the
 *    class_exists() walk below hard-crashes the suite on regression).
 */
class ConsoleCommandsTest extends TestCase
{
    public function test_every_registered_command_class_exists(): void
    {
        foreach ($this->registeredCommands() as $name => $class) {
            $this->assertTrue(
                class_exists($class),
                "Console command '{$name}' maps to missing class {$class}."
            );
            $this->assertTrue(
                is_subclass_of($class, Command::class),
                "Console command '{$name}' class {$class} does not extend Command."
            );
        }
    }

    public function test_queue_worker_commands_are_registered(): void
    {
        $commands = $this->registeredCommands();

        $this->assertArrayHasKey('queue:work', $commands);
        $this->assertArrayHasKey('queue:table', $commands);
        $this->assertArrayNotHasKey('queue:test', $commands);
    }

    public function test_queue_work_command_is_instantiable(): void
    {
        // This line alone fatals if the execute() signature regresses.
        $cmd = new \Framework\Core\Console\Commands\QueueWorkCommand(
            ['console', 'queue:work', '--silent']
        );
        $this->assertInstanceOf(Command::class, $cmd);
    }

    /** @return array<string, class-string> */
    private function registeredCommands(): array
    {
        $console = new Console(['console']);
        $prop = (new \ReflectionClass($console))->getProperty('commands');
        $prop->setAccessible(true);
        return $prop->getValue($console);
    }
}
