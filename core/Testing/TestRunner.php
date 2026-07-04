<?php

namespace Framework\Core\Testing;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Discovers and runs {@see TestCase} subclasses, then prints a report.
 *
 * Zero-dependency, PHPUnit-flavoured. Discovery is by file convention
 * (`*Test.php`) and, crucially, by *class* — after including a file we
 * diff the declared-class list and keep any concrete TestCase subclass.
 * That avoids brittle path→FQCN guessing and works regardless of the
 * test's namespace.
 *
 * Usage lives in {@see \Framework\Core\Console\Commands\TestRunCommand}.
 */
class TestRunner
{
    private string $path;
    private ?string $filter;
    private bool $stopOnFailure;
    private bool $colors;

    /** @var array<int, array{class:string, method:string, message:string, trace:string}> */
    private array $failures = [];
    /** @var array<int, array{class:string, method:string, message:string, trace:string}> */
    private array $errors = [];
    /** @var array<int, array{class:string, method:string, message:string}> */
    private array $skipped = [];

    private int $tests = 0;
    private int $assertions = 0;

    /**
     * @param string      $path          File or directory to scan for tests.
     * @param string|null $filter        Only run methods/classes whose
     *                                   "Class::method" contains this string.
     * @param bool        $stopOnFailure Halt on the first failure or error.
     * @param bool        $colors        Emit ANSI colour codes.
     */
    public function __construct(
        string $path,
        ?string $filter = null,
        bool $stopOnFailure = false,
        bool $colors = true
    ) {
        $this->path = $path;
        $this->filter = $filter;
        $this->stopOnFailure = $stopOnFailure;
        $this->colors = $colors;
    }

    /**
     * Run the suite.
     *
     * @return int Process exit code: 0 if everything passed, 1 otherwise.
     */
    public function run(): int
    {
        if (!file_exists($this->path)) {
            $this->writeln($this->color("No test path found: {$this->path}", '31'));
            return 1;
        }

        $classes = $this->discover();

        if ($classes === []) {
            $this->writeln($this->color("No tests found in {$this->path}", '33'));
            return 0;
        }

        $start = microtime(true);

        foreach ($classes as $class) {
            if (!$this->runClass($class)) {
                break; // stop-on-failure tripped
            }
        }

        $this->report(microtime(true) - $start);

        return ($this->failures === [] && $this->errors === []) ? 0 : 1;
    }

    // ── Discovery ───────────────────────────────────────────────────────

    /**
     * @return array<int, class-string<TestCase>>
     */
    private function discover(): array
    {
        $files = is_dir($this->path)
            ? $this->scanDirectory($this->path)
            : [$this->path];

        $found = [];
        foreach ($files as $file) {
            $before = get_declared_classes();
            require_once $file;
            $new = array_diff(get_declared_classes(), $before);

            foreach ($new as $class) {
                if (!is_subclass_of($class, TestCase::class)) {
                    continue;
                }
                $ref = new \ReflectionClass($class);
                if ($ref->isAbstract()) {
                    continue;
                }
                $found[$class] = $class; // de-dupe
            }
        }

        return array_values($found);
    }

    /**
     * @return array<int, string>
     */
    private function scanDirectory(string $dir): array
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $files = [];
        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isFile() && preg_match('/Test\.php$/', $file->getFilename())) {
                $files[] = $file->getPathname();
            }
        }
        sort($files); // deterministic order
        return $files;
    }

    // ── Execution ───────────────────────────────────────────────────────

    /**
     * @param class-string<TestCase> $class
     * @return bool false if the run should stop (stop-on-failure)
     */
    private function runClass(string $class): bool
    {
        foreach ($this->testMethods($class) as $method) {
            $label = $class . '::' . $method;

            if ($this->filter !== null && !str_contains($label, $this->filter)) {
                continue;
            }

            $this->tests++;

            try {
                $instance = new $class();
                $instance->run($method);
                $this->assertions += $instance->numberOfAssertions();
                $this->write($this->color('.', '32'));
            } catch (SkippedTestException $e) {
                $this->skipped[] = [
                    'class' => $class, 'method' => $method, 'message' => $e->getMessage(),
                ];
                $this->write($this->color('S', '33'));
            } catch (AssertionFailedException $e) {
                if (isset($instance)) {
                    $this->assertions += $instance->numberOfAssertions();
                }
                $this->failures[] = [
                    'class' => $class, 'method' => $method,
                    'message' => $e->getMessage(), 'trace' => $this->firstUserFrame($e),
                ];
                $this->write($this->color('F', '31'));
                if ($this->stopOnFailure) {
                    return false;
                }
            } catch (\Throwable $e) {
                $this->errors[] = [
                    'class' => $class, 'method' => $method,
                    'message' => get_class($e) . ': ' . $e->getMessage(),
                    'trace' => $this->firstUserFrame($e),
                ];
                $this->write($this->color('E', '31'));
                if ($this->stopOnFailure) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Public, non-inherited methods named test* on the given class.
     *
     * @param class-string<TestCase> $class
     * @return array<int, string>
     */
    private function testMethods(string $class): array
    {
        $ref = new \ReflectionClass($class);
        $methods = [];
        foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() === TestCase::class) {
                continue; // skip inherited helpers (run, get, post, …)
            }
            if (str_starts_with($method->getName(), 'test')) {
                $methods[] = $method->getName();
            }
        }
        return $methods;
    }

    // ── Reporting ───────────────────────────────────────────────────────

    private function report(float $seconds): void
    {
        $this->writeln("\n");

        foreach (['FAILURES' => $this->failures, 'ERRORS' => $this->errors] as $heading => $items) {
            if ($items === []) {
                continue;
            }
            $this->writeln($this->color($heading . ':', '31'));
            foreach ($items as $i => $item) {
                $n = $i + 1;
                $this->writeln("\n  {$n}) {$item['class']}::{$item['method']}");
                $this->writeln('     ' . str_replace("\n", "\n     ", $item['message']));
                if ($item['trace'] !== '') {
                    $this->writeln($this->color('     ' . $item['trace'], '90'));
                }
            }
            $this->writeln('');
        }

        if ($this->skipped !== []) {
            $this->writeln($this->color('SKIPPED:', '33'));
            foreach ($this->skipped as $item) {
                $this->writeln("  - {$item['class']}::{$item['method']}: {$item['message']}");
            }
            $this->writeln('');
        }

        $summary = sprintf(
            'Tests: %d, Assertions: %d, Failures: %d, Errors: %d, Skipped: %d  (%.3fs)',
            $this->tests, $this->assertions,
            count($this->failures), count($this->errors), count($this->skipped),
            $seconds
        );

        $ok = $this->failures === [] && $this->errors === [];
        $this->writeln($this->color($summary, $ok ? '32' : '31'));
        $this->writeln($this->color($ok ? 'OK' : 'FAILED', $ok ? '32' : '31'));
    }

    /** Best-effort "file:line" of the frame that triggered the failure. */
    private function firstUserFrame(\Throwable $e): string
    {
        // If the exception originates in user code (e.g. a thrown
        // exception in a test method), that location is what we want.
        if (!$this->isInternalFrame($e->getFile())) {
            return $e->getFile() . ':' . $e->getLine();
        }

        // Otherwise it was thrown from the assertion library — walk the
        // stack out to the first frame that lives in the user's code.
        foreach ($e->getTrace() as $frame) {
            if (isset($frame['file']) && !$this->isInternalFrame($frame['file'])) {
                return $frame['file'] . ':' . ($frame['line'] ?? 0);
            }
        }

        return ''; // nothing meaningful to point at
    }

    private function isInternalFrame(string $file): bool
    {
        return str_contains($file, '/core/Testing/')
            || str_contains($file, '/core/Console/')
            || str_contains($file, '/bootstrap/')
            || str_ends_with($file, '/console');
    }

    // ── Output ──────────────────────────────────────────────────────────

    private function write(string $s): void
    {
        fwrite(STDOUT, $s);
    }

    private function writeln(string $s): void
    {
        fwrite(STDOUT, $s . "\n");
    }

    private function color(string $text, string $code): string
    {
        return $this->colors ? "\033[{$code}m{$text}\033[0m" : $text;
    }
}
