<?php

/**
 * =============================================================================
 * ClosureSerializer — Extract, serialize, and reconstruct PHP Closures
 * =============================================================================
 *
 * Works by using ReflectionFunction to locate the closure's source file and
 * line range, then token_get_all() to extract the exact source code. The
 * captured `use()` variables are serialized alongside the source so they can
 * be reinjected on the receiving end via `extract()` before eval().
 *
 * Limitations (by design — no third-party deps):
 *  - The closure source file must exist and be readable on the SERVER side.
 *  - Captured variables must be natively serializable (arrays, scalars,
 *    plain objects with no resource handles, Eloquent models, etc.).
 *  - `$this` binding is NOT supported (static closures only, or closures
 *    that do not reference $this).
 *  - Nested closures inside the closure body are extracted verbatim and will
 *    work as long as they don't themselves capture outer-scope variables that
 *    weren't already in the `use()` list.
 *
 * @package Framework\Core\Support
 */

namespace Framework\Core\Support;

class ClosureSerializer
{
    // ─── Public API ──────────────────────────────────────────────────────

    /**
     * Serialize a Closure into a portable string.
     *
     * The returned string can be stored, sent over a socket, put in a queue,
     * etc. Pass it to deserialize() on the other end to get a callable back.
     *
     * @param  \Closure $closure
     * @return string   Base64-encoded, JSON-wrapped serialized closure
     *
     * @throws \RuntimeException  If the source cannot be read or extracted
     * @throws \RuntimeException  If any captured variable is not serializable
     */
    public static function serialize(\Closure $closure): string
    {
        $rf = new \ReflectionFunction($closure);

        // ── 1. Extract source code ───────────────────────────────────────
        $source = self::extractSource($rf);

        // ── 2. Capture `use()` variables ────────────────────────────────
        $uses = [];
        foreach ($rf->getStaticVariables() as $name => $value) {
            // Verify each captured variable can survive a round-trip
            self::assertSerializable($name, $value);
            $uses[$name] = serialize($value);
        }

        // ── 3. Pack into a JSON envelope and base64-encode ───────────────
        $envelope = json_encode([
            'source' => $source,
            'uses'   => $uses,
        ]);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('ClosureSerializer: JSON encode failed — ' . json_last_error_msg());
        }

        return base64_encode($envelope);
    }

    /**
     * Reconstruct a Closure from a serialized string produced by serialize().
     *
     * @param  string   $data  The base64-encoded envelope from serialize()
     * @return \Closure
     *
     * @throws \RuntimeException  If the data is corrupt or eval() fails
     */
    public static function deserialize(string $data): \Closure
    {
        // ── 1. Decode & unpack ───────────────────────────────────────────
        $json = base64_decode($data, true);
        if ($json === false) {
            throw new \RuntimeException('ClosureSerializer: base64 decode failed — data is corrupt.');
        }

        $envelope = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
             throw new \RuntimeException('ClosureSerializer: JSON decode failed — ' . json_last_error_msg());
        }

        $source = $envelope['source'] ?? null;
        $uses   = $envelope['uses']   ?? [];

        if (!is_string($source) || trim($source) === '') {
            throw new \RuntimeException('ClosureSerializer: missing or empty source in envelope.');
        }

        // ── 2. Restore captured variables into local scope ───────────────
        //    extract() puts them as local variables, which the eval'd closure
        //    can then reference normally.
        $restored = [];
        foreach ($uses as $name => $serialized) {
            $restored[$name] = unserialize($serialized);
        }
        extract($restored, EXTR_SKIP); // EXTR_SKIP: never overwrite existing locals

        // ── 3. Evaluate the closure source ──────────────────────────────
        //    The source already contains the `use (...)` clause extracted
        //    from the original file, so variables are bound automatically.
        $closure = null;
        try {
            // Wrap in an immediately-invoked assignment so we can capture it
            eval('$closure = ' . $source . ';');
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'ClosureSerializer: eval() failed — ' . $e->getMessage() . "\n\nSource:\n" . $source,
                0,
                $e
            );
        }

        if (!($closure instanceof \Closure)) {
            throw new \RuntimeException('ClosureSerializer: eval() did not produce a Closure.');
        }

        return $closure;
    }

    // ─── Private Helpers ─────────────────────────────────────────────────

    /**
     * Use ReflectionFunction + token_get_all() to extract the exact closure
     * source text from the file it was defined in.
     *
     * Strategy:
     *  1. Tokenize the whole file.
     *  2. Walk tokens tracking brace depth starting from the closure's
     *     opening line, collecting everything until the closing `}` that
     *     returns us to depth 0.
     *
     * @throws \RuntimeException
     */
    private static function extractSource(\ReflectionFunction $rf): string
    {
        $file      = $rf->getFileName();
        $startLine = $rf->getStartLine(); // 1-based, inclusive
        $endLine   = $rf->getEndLine();   // 1-based, inclusive

        if ($file === false || !is_file($file)) {
            throw new \RuntimeException(
                'ClosureSerializer: cannot read source — closure was not defined in a file ' .
                '(defined in eval\'d code, REPL, or a stream wrapper).'
            );
        }

        $source  = file_get_contents($file);
        $tokens  = token_get_all($source);

        $result     = '';
        $depth      = 0;
        $capturing  = false;
        $foundOpen  = false; // did we see the first `{` yet?

        // PHP 7.4 arrow function constant check
        $t_fn = defined('T_FN') ? T_FN : 10000;

        foreach ($tokens as $token) {
            // Normalise: string tokens have no line number
            if (is_array($token)) {
                [$id, $text, $line] = $token;
            } else {
                $text = $token;
                $line = null; // single-char tokens inherit current position
            }

            // Wait until we reach the closure's starting line
            if (!$capturing) {
                if (is_array($token) && $line >= $startLine) {
                    // Look for T_FUNCTION or T_FN (arrow functions) or T_STATIC
                    // to identify the closure keyword on or after the start line
                    if (in_array($id, [T_FUNCTION, $t_fn, T_STATIC], true)) {
                        $capturing = true;
                        $result   .= $text;
                    }
                }
                continue;
            }

            // We are capturing — accumulate everything
            $result .= $text;

            // Track brace depth to find the matching closing brace
            if ($text === '{') {
                $depth++;
                $foundOpen = true;
            } elseif ($text === '}') {
                $depth--;
                if ($foundOpen && $depth === 0) {
                    break; // done — we have the full closure
                }
            }

            // Arrow functions: body is a single expression, no braces.
            // They end at the first `,`, `)`, or `;` at depth 0.
            // We detect T_FN by checking if we never opened a `{`.
            // (Arrow function bodies have depth==0 the entire time.)
        }

        $result = trim($result);

        if ($result === '') {
            throw new \RuntimeException(
                "ClosureSerializer: failed to extract closure source from {$file} " .
                "(lines {$startLine}–{$endLine})."
            );
        }

        return $result;
    }

    /**
     * Convenience: serialize a Closure and wrap it in the envelope shape
     * that QueueClient::push() expects when type === 'closure'.
     *
     * QueueClient calls this internally — you don't need to call it yourself.
     *
     * @return array{type: 'closure', data: string, args: array}
     */
    public static function toPayload(\Closure $closure, array $args = []): array
    {
        return [
            'type' => 'closure',
            'data' => self::serialize($closure),
            'args' => $args,
        ];
    }

    /**
     * Reconstruct and invoke a closure from a QueueServer-received payload.
     *
     * Call this on the SERVER side after receiving a job with type === 'closure'.
     *
     * @param array{type: string, data: string, args: array} $payload
     */
    public static function invokeFromPayload(array $payload)
    {
        $closure = self::deserialize($payload['data']);
        return $closure(...($payload['args'] ?? []));
    }

    /**
     * Throw a descriptive error if a captured variable cannot be serialized.
     *
     * @throws \RuntimeException
     */
    private static function assertSerializable(string $name, $value): void
    {
        // Closures, resources, and some internal objects are not serializable
        if ($value instanceof \Closure) {
            throw new \RuntimeException(
                "ClosureSerializer: captured variable \${$name} is itself a Closure. " .
                'Nested closure capture is not supported — extract the inner closure ' .
                'to a named function or serialize it separately.'
            );
        }

        if (is_resource($value)) {
            throw new \RuntimeException(
                "ClosureSerializer: captured variable \${$name} is a resource handle " .
                '(e.g. a database connection, file handle). Resources cannot be serialized.'
            );
        }

        try {
            serialize($value);
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                "ClosureSerializer: captured variable \${$name} is not serializable — " .
                $e->getMessage(),
                0,
                $e
            );
        }
    }
}
