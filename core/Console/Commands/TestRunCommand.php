<?php

namespace Framework\Core\Console\Commands;

use Framework\Core\Console\Command;
use Framework\Core\Testing\TestRunner;

/**
 * `php console test` — run the application test suite.
 *
 *   php console test                       # run everything in tests/
 *   php console test tests/Unit            # run a subdirectory or file
 *   php console test --filter=Encryption   # only Class::method matching
 *   php console test --stop-on-failure     # halt on first failure/error
 *   php console test --no-color            # plain output (CI logs)
 */
class TestRunCommand extends Command
{
    protected string $signature = 'test';
    protected string $description = 'Run the application test suite';

    public function execute(): void
    {
        $root = dirname(__DIR__, 3);

        // Optional positional arg: a path relative to the project root
        // (or an absolute path). Defaults to the tests/ directory.
        $target = $this->argument(0, 'tests');
        $path = $this->isAbsolute($target) ? $target : $root . '/' . ltrim($target, '/');

        $filter = $this->option('filter');
        $stopOnFailure = (bool) $this->option('stop-on-failure', false);
        $colors = !$this->option('no-color', false);

        $this->info("Framework Test Runner");
        $this->line(str_repeat('=', 30));

        $runner = new TestRunner(
            $path,
            is_string($filter) ? $filter : null,
            $stopOnFailure,
            $colors
        );

        // Exit with the runner's status so CI can gate on it.
        exit($runner->run());
    }

    private function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }
}
