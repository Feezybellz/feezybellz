<?php

namespace Framework\Core\Console\Commands;

use Framework\Core\Console\Command;

/**
 * make:env — generate a .env file from what config/*.php actually reads.
 *
 * The command performs static analysis on every config file (via
 * token_get_all — no execution, no side effects) to collect every
 * env('KEY', default) call. The result is a categorized .env file with
 * one section per source config file.
 *
 * This means: if you add env('SOMETHING_NEW', ...) to any config, this
 * command surfaces it on the next run. No hand-maintained template to
 * drift out of sync.
 *
 * Options:
 *   --example      Write to .env.example (safe default template).
 *   --to=<path>    Write to a custom path (default is --example's target
 *                  or .env when combined with --force / --merge).
 *   --force        Overwrite the target if it exists. Required to write .env.
 *   --merge        Read existing target, keep every value it already has,
 *                  add only missing keys. Never overwrites a set value.
 *   --generate-key Force a fresh APP_KEY even if one already exists.
 *   --interactive  Old prompt-driven mode. Kept as a fallback.
 *   --silent       Suppress info/success output (errors still print).
 */
class MakeEnvCommand extends Command
{
    public function execute(): void
    {
        if ($this->option('interactive') || $this->option('i')) {
            $this->runInteractive();
            return;
        }

        $rootPath = dirname(dirname(dirname(__DIR__)));
        $configDir = $rootPath . '/config';

        if (!is_dir($configDir)) {
            $this->error("Config directory not found at {$configDir}");
            return;
        }

        // Resolve target path. Precedence: --to > --example > .env
        // Absolute --to paths are respected as-is; relative are project-relative.
        if ($this->option('to')) {
            $to = $this->option('to');
            $target = ($to !== '' && $to[0] === '/') ? $to : ($rootPath . '/' . ltrim($to, '/'));
        } elseif ($this->option('example')) {
            $target = $rootPath . '/.env.example';
        } else {
            $target = $rootPath . '/.env';
        }
        $isExample = str_ends_with($target, '.env.example');

        $merge = (bool) $this->option('merge');
        $force = (bool) $this->option('force');

        if (file_exists($target) && !$force && !$merge) {
            $this->error("Target {$target} already exists.");
            $this->info("Use --force to overwrite, or --merge to add only missing keys.");
            return;
        }

        // Scan all config files.
        $sections = $this->scanConfigDir($configDir);
        $allKeys = $this->flattenKeys($sections);

        // Load existing target so merge can preserve values.
        $existing = file_exists($target) ? $this->parseExistingEnv($target) : [];

        // Optionally generate APP_KEY. Only when writing a real .env
        // (never .env.example — no fake secrets in git). Forced values
        // override BOTH the extracted default AND the existing value —
        // that's the whole point of --generate-key.
        $forced = [];
        if (!$isExample && array_key_exists('APP_KEY', $allKeys)) {
            $existingKey = trim((string) ($existing['APP_KEY'] ?? ''));
            $shouldGenKey = $this->option('generate-key') || $existingKey === '';
            if ($shouldGenKey) {
                $forced['APP_KEY'] = 'base64:' . base64_encode(random_bytes(32));
                $this->info("Generated a new APP_KEY.");
            }
        }

        // Render.
        $output = $this->render($sections, $existing, $forced, $merge, $isExample);

        file_put_contents($target, $output);

        $keyCount = count($allKeys);
        $this->success("Wrote {$target} ({$keyCount} env keys across " . count($sections) . " config files).");

        if (!$isExample && $merge) {
            $added = count(array_diff_key($allKeys, $existing));
            if ($added > 0) {
                $this->info("Added {$added} missing key(s); preserved existing values.");
            } else {
                $this->info("No missing keys found.");
            }
        }
    }

    /**
     * Walk config/*.php, extract every env() call from each file.
     *
     * @return array<string, array<int, array{key: string, default: mixed, has_default: bool}>>
     *   Keyed by basename (e.g. 'app.php'), each value is a list of extracted calls.
     */
    protected function scanConfigDir(string $dir): array
    {
        $sections = [];
        $files = glob($dir . '/*.php');
        sort($files); // deterministic order: app.php, cache.php, ...

        foreach ($files as $file) {
            $entries = $this->extractEnvCalls($file);
            if (!empty($entries)) {
                $sections[basename($file)] = $entries;
            }
        }

        // Also scan .env.example as a fallback source of known keys, so
        // things a config file uses only as env('KEY') (no default) still
        // show up with their example value.
        $examplePath = dirname($dir) . '/.env.example';
        if (file_exists($examplePath)) {
            $exampleParsed = $this->parseExistingEnv($examplePath);
            $seen = $this->flattenKeys($sections);
            $extras = [];
            foreach ($exampleParsed as $k => $v) {
                if (!isset($seen[$k])) {
                    $extras[] = ['key' => $k, 'default' => $v, 'has_default' => true];
                }
            }
            if (!empty($extras)) {
                $sections['.env.example'] = $extras;
            }
        }

        return $sections;
    }

    /**
     * Static extraction of `env('KEY', default)` from a PHP file.
     * Uses tokens rather than eval'ing the file, so requiring the config
     * doesn't have to be side-effect-free.
     *
     * Handled shapes:
     *   env('KEY')                → key, no default
     *   env('KEY', 'literal')     → key, string default
     *   env('KEY', 123)           → key, numeric default
     *   env('KEY', true|false)    → key, boolean default
     *   env('KEY', null)          → key, null default
     *   env('KEY', env('OTHER'))  → key, no default (nested — too complex to inline)
     *   env('KEY', constant)      → key, no default
     *
     * @return array<int, array{key: string, default: mixed, has_default: bool}>
     */
    protected function extractEnvCalls(string $file): array
    {
        $source = @file_get_contents($file);
        if (!is_string($source) || $source === '') {
            return [];
        }

        $tokens = @token_get_all($source);
        if (!is_array($tokens)) {
            return [];
        }

        $results = [];
        $count = count($tokens);
        $seen = [];

        for ($i = 0; $i < $count; $i++) {
            $t = $tokens[$i];
            if (!is_array($t) || $t[0] !== T_STRING || $t[1] !== 'env') {
                continue;
            }

            // Make sure this is a function call, not a method or namespaced.
            // Reject A->env, A::env, \Ns\env.
            $prev = $this->prevSignificantToken($tokens, $i);
            if ($prev !== null) {
                $prevTok = $tokens[$prev];
                if (is_array($prevTok) && in_array($prevTok[0], [T_OBJECT_OPERATOR, T_PAAMAYIM_NEKUDOTAYIM, T_NS_SEPARATOR, T_DOUBLE_COLON ?? -1], true)) {
                    continue;
                }
            }

            // Next significant token must be '('
            $next = $this->nextSignificantToken($tokens, $i);
            if ($next === null || $tokens[$next] !== '(') {
                continue;
            }

            // Extract top-level args between matched parens.
            $args = $this->readCallArgs($tokens, $next);
            if (empty($args)) {
                continue;
            }

            // First arg must be a single T_CONSTANT_ENCAPSED_STRING (a
            // literal string). Dynamic keys (`env($whatever)`) are skipped.
            $keyToken = $this->reduceToSingleToken($args[0]);
            if ($keyToken === null || !is_array($keyToken) || $keyToken[0] !== T_CONSTANT_ENCAPSED_STRING) {
                continue;
            }
            $key = trim($keyToken[1], "'\"");
            if ($key === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key)) {
                continue;
            }

            // First-wins: multiple env() calls with the same key take the
            // first extracted default.
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $default = null;
            $hasDefault = false;
            if (isset($args[1])) {
                $default = $this->tokensToLiteral($args[1]);
                $hasDefault = $default !== self::MISSING;
                if (!$hasDefault) {
                    $default = null;
                }
            }

            $results[] = [
                'key' => $key,
                'default' => $default,
                'has_default' => $hasDefault,
            ];
        }

        return $results;
    }

    private const MISSING = "\0__MISSING__\0";

    /**
     * @param array $tokens
     * @param int $i     Index of the '(' token
     * @return array<int, array> Each element is the raw token stream of one argument.
     */
    private function readCallArgs(array $tokens, int $i): array
    {
        $depth = 0;
        $currentArg = [];
        $args = [];
        $count = count($tokens);

        for ($j = $i; $j < $count; $j++) {
            $tok = $tokens[$j];
            $char = is_string($tok) ? $tok : null;

            if ($char === '(') {
                $depth++;
                if ($depth === 1) continue; // don't include the opening paren
            } elseif ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    if (!empty($currentArg) || !empty($args)) {
                        $args[] = $currentArg;
                    }
                    return $args;
                }
            } elseif ($char === ',' && $depth === 1) {
                $args[] = $currentArg;
                $currentArg = [];
                continue;
            }

            if ($depth >= 1) {
                $currentArg[] = $tok;
            }
        }
        return $args;
    }

    /**
     * If an arg-token list contains exactly one non-whitespace token, return it.
     * Otherwise null (means the arg is a complex expression).
     */
    private function reduceToSingleToken(array $argTokens)
    {
        $sig = [];
        foreach ($argTokens as $t) {
            if (is_array($t) && in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $sig[] = $t;
            if (count($sig) > 1) return null;
        }
        return $sig[0] ?? null;
    }

    /**
     * Convert an arg-token list into a PHP literal (string, int, float,
     * bool, null), or MISSING if the expression is too complex to represent
     * as a literal (e.g. nested env() calls, concatenations, constants).
     *
     * @return mixed
     */
    private function tokensToLiteral(array $argTokens)
    {
        $tok = $this->reduceToSingleToken($argTokens);
        if ($tok === null) {
            return self::MISSING;
        }
        if (is_array($tok)) {
            switch ($tok[0]) {
                case T_CONSTANT_ENCAPSED_STRING:
                    return trim($tok[1], "'\"");
                case T_LNUMBER:
                    return (int) $tok[1];
                case T_DNUMBER:
                    return (float) $tok[1];
                case T_STRING:
                    $l = strtolower($tok[1]);
                    if ($l === 'true')  return true;
                    if ($l === 'false') return false;
                    if ($l === 'null')  return null;
                    return self::MISSING; // some other constant we can't resolve
            }
        }
        return self::MISSING;
    }

    /** Return index of previous non-whitespace/non-comment token, or null. */
    private function prevSignificantToken(array $tokens, int $i): ?int
    {
        for ($j = $i - 1; $j >= 0; $j--) {
            $t = $tokens[$j];
            if (is_array($t) && in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) continue;
            return $j;
        }
        return null;
    }

    /** Return index of next non-whitespace/non-comment token, or null. */
    private function nextSignificantToken(array $tokens, int $i): ?int
    {
        $count = count($tokens);
        for ($j = $i + 1; $j < $count; $j++) {
            $t = $tokens[$j];
            if (is_array($t) && in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) continue;
            return $j;
        }
        return null;
    }

    /**
     * Flatten section-grouped entries into a single [key => default] map.
     * Later sections don't overwrite earlier ones (first-seen wins).
     *
     * @return array<string, mixed>
     */
    private function flattenKeys(array $sections): array
    {
        $flat = [];
        foreach ($sections as $entries) {
            foreach ($entries as $entry) {
                if (!isset($flat[$entry['key']])) {
                    $flat[$entry['key']] = $entry['default'] ?? null;
                }
            }
        }
        return $flat;
    }

    /**
     * Parse a simple .env file into [key => raw value] pairs. Doesn't try
     * to interpret ${VAR} references — just reads what's on the line.
     *
     * @return array<string, string>
     */
    private function parseExistingEnv(string $path): array
    {
        $result = [];
        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) return $result;

        foreach ($lines as $line) {
            $trim = trim($line);
            if ($trim === '' || $trim[0] === '#') continue;
            if (!str_contains($line, '=')) continue;

            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            // Strip inline comments and surrounding quotes; leave other
            // whitespace intact so multi-word values with quotes survive.
            $value = $this->stripInlineComment($value);
            $value = trim($value);
            if ((str_starts_with($value, '"') && str_ends_with($value, '"'))
                || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }
            if ($name !== '') {
                $result[$name] = $value;
            }
        }
        return $result;
    }

    private function stripInlineComment(string $value): string
    {
        // Only strip a #-comment when it's NOT inside quotes. Cheap check:
        // if we see an unquoted # after content, drop it and everything after.
        $out = '';
        $inSingle = false;
        $inDouble = false;
        $len = strlen($value);
        for ($i = 0; $i < $len; $i++) {
            $c = $value[$i];
            if ($c === "'" && !$inDouble) $inSingle = !$inSingle;
            elseif ($c === '"' && !$inSingle) $inDouble = !$inDouble;
            elseif ($c === '#' && !$inSingle && !$inDouble) break;
            $out .= $c;
        }
        return $out;
    }

    /**
     * Assemble the final .env text. Sections in config-file order; each
     * section header names its source. Values come from existing target
     * (when --merge) or from the extracted defaults.
     */
    private function render(array $sections, array $existing, array $forced, bool $merge, bool $isExample): string
    {
        $out = "# Generated by `php console make:env`\n";
        $out .= "# Source: config/*.php (statically scanned) + .env.example (fallback)\n";
        $out .= "# Regenerate anytime — this file's format is intentionally boring.\n\n";

        $writtenKeys = [];

        foreach ($sections as $file => $entries) {
            $lines = [];
            foreach ($entries as $entry) {
                $key = $entry['key'];
                if (isset($writtenKeys[$key])) continue;

                // Value precedence:
                //   1. forced (e.g. auto-generated APP_KEY) — always wins
                //   2. existing value when --merge — preserve edits
                //   3. extracted default — the config's fallback
                //   4. "" — no default anywhere
                if (array_key_exists($key, $forced)) {
                    $rendered = $this->formatValue($forced[$key]);
                } elseif ($merge && array_key_exists($key, $existing)) {
                    $rendered = $this->formatValue($existing[$key]);
                } elseif ($entry['has_default']) {
                    $rendered = $this->formatValue($entry['default']);
                } else {
                    $rendered = '';
                }

                $lines[] = "{$key}={$rendered}";
                $writtenKeys[$key] = true;
            }

            if (empty($lines)) continue;

            $out .= "# " . str_repeat('-', 68) . "\n";
            $out .= "# " . $file . "\n";
            $out .= "# " . str_repeat('-', 68) . "\n";
            $out .= implode("\n", $lines) . "\n\n";
        }

        // Any keys the existing target had that we haven't emitted yet —
        // preserve them at the end so nothing is silently dropped.
        $orphans = array_diff_key($existing, $writtenKeys);
        if (!empty($orphans)) {
            $out .= "# " . str_repeat('-', 68) . "\n";
            $out .= "# App-defined (not referenced by any config file)\n";
            $out .= "# " . str_repeat('-', 68) . "\n";
            foreach ($orphans as $key => $value) {
                $out .= "{$key}=" . $this->formatValue($value) . "\n";
            }
            $out .= "\n";
        }

        return $out;
    }

    /**
     * Format a PHP literal for .env output.
     */
    private function formatValue($value): string
    {
        if ($value === null) return '';
        if ($value === true) return 'true';
        if ($value === false) return 'false';
        if (is_int($value) || is_float($value)) return (string) $value;

        $s = (string) $value;
        // Quote if it contains whitespace, #, or leading/trailing spaces.
        if ($s === '' || preg_match('/^\s|\s$|[#\s]/', $s)) {
            return '"' . str_replace('"', '\\"', $s) . '"';
        }
        return $s;
    }

    // ─── Interactive fallback (previous behavior, preserved) ──────────

    private function runInteractive(): void
    {
        $rootPath = dirname(dirname(dirname(__DIR__)));
        $envPath = $rootPath . '/.env';

        if (file_exists($envPath) && !$this->option('force')) {
            $this->error('.env file already exists!');
            $this->info('Use --force to overwrite the existing file.');
            return;
        }

        $this->info('Creating .env file interactively...');
        $this->info('Press Enter to use default values shown in [brackets]');
        echo "\n";

        $config = [
            'APP_NAME'     => $this->ask('Application name', 'Framework'),
            'APP_ENV'      => $this->ask('Environment (local/production)', 'local'),
            'APP_DEBUG'    => $this->ask('Debug mode (true/false)', 'true'),
            'APP_URL'      => $this->ask('Application URL', 'http://localhost:8000'),
            'APP_KEY'      => 'base64:' . base64_encode(random_bytes(32)),
            'DB_CONNECTION'=> $this->ask('Database connection (mysql/pgsql/sqlite/mongodb)', 'mysql'),
            'DB_HOST'      => $this->ask('Database host', '127.0.0.1'),
            'DB_DATABASE'  => $this->ask('Database name', 'framework'),
            'DB_USERNAME'  => $this->ask('Database username', 'root'),
            'DB_PASSWORD'  => $this->ask('Database password', ''),
        ];

        $content = "# Generated interactively by `php console make:env --interactive`\n\n";
        foreach ($config as $k => $v) {
            $content .= "{$k}=" . $this->formatValue($v) . "\n";
        }

        file_put_contents($envPath, $content);
        $this->success('.env file created interactively.');
    }

    private function ask(string $question, ?string $default = null): string
    {
        $prompt = $default !== null ? "  {$question} [{$default}]: " : "  {$question}: ";
        echo $prompt;
        $answer = trim((string) fgets(STDIN));
        return $answer !== '' ? $answer : ($default ?? '');
    }
}
