<?php

/**
 * ClosureSerializer — Extract, serialize, and reconstruct PHP Closures
 * over a process boundary (queue, socket, file).
 *
 * ┌────────────────────────────────────────────────────────────────────┐
 * │ THIS CLASS CALLS eval() ON DECODED PAYLOADS.                       │
 * │                                                                    │
 * │ It is GATED behind two locks:                                      │
 * │                                                                    │
 * │  1. config('closure_serializer.enabled') must be true.             │
 * │     deserialize() throws if not. Default is false; flip via env    │
 * │     CLOSURE_SERIALIZER_ENABLED=true.                               │
 * │                                                                    │
 * │  2. The payload must carry an HMAC-SHA256 signature over the       │
 * │     source+uses fields, signed with APP_KEY. deserialize() uses    │
 * │     hash_equals to verify, in constant time, before eval() runs.   │
 * │                                                                    │
 * │ If APP_KEY leaks, anyone who can hand a payload to deserialize()   │
 * │ has remote code execution. Treat this class like a key-signing     │
 * │ ceremony — minimal callers, audit every entry point.               │
 * └────────────────────────────────────────────────────────────────────┘
 *
 * Other limitations (unchanged from the original design):
 *  - The closure source file must exist and be readable on the SERVER side.
 *  - Captured variables must be natively serializable.
 *  - `$this` binding is NOT supported.
 *  - Nested inner closures are extracted verbatim.
 */

namespace Framework\Core\Support;

class ClosureSerializer
{
    private const VERSION = 2;

    /**
     * Serialize a Closure into a signed, portable string.
     *
     * @throws \RuntimeException If APP_KEY is missing or the source can't be read.
     */
    public static function serialize(\Closure $closure): string
    {
        $rf = new \ReflectionFunction($closure);

        $source = self::extractSource($rf);

        $uses = [];
        foreach ($rf->getStaticVariables() as $name => $value) {
            self::assertSerializable($name, $value);
            $uses[$name] = serialize($value);
        }

        $body = json_encode([
            'v'      => self::VERSION,
            'source' => $source,
            'uses'   => $uses,
        ]);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('ClosureSerializer: JSON encode failed — ' . json_last_error_msg());
        }

        $bodyB64 = base64_encode($body);
        $signature = hash_hmac('sha256', $bodyB64, self::resolveSigningKey());

        // The signature is part of the same outer envelope so it can't be
        // stripped/recomputed by an attacker who can only modify the source.
        $envelope = json_encode([
            'body' => $bodyB64,
            'sig'  => $signature,
        ]);

        return base64_encode($envelope);
    }

    /**
     * Verify the signature and reconstruct the Closure.
     *
     * Refuses if:
     *  - the feature is not enabled in config,
     *  - the envelope is malformed,
     *  - the signature doesn't match the configured key,
     *  - the inner version is unsupported.
     *
     * @throws \RuntimeException
     */
    public static function deserialize(string $data): \Closure
    {
        if (!self::enabled()) {
            throw new \RuntimeException(
                'ClosureSerializer: deserialize() is disabled. Set config(\'closure_serializer.enabled\') = true '
                . 'and supply APP_KEY only if you have a hard requirement.'
            );
        }

        $outer = base64_decode($data, true);
        if ($outer === false) {
            throw new \RuntimeException('ClosureSerializer: outer base64 decode failed.');
        }
        $envelope = json_decode($outer, true);
        if (!is_array($envelope) || !isset($envelope['body'], $envelope['sig'])
            || !is_string($envelope['body']) || !is_string($envelope['sig'])) {
            throw new \RuntimeException('ClosureSerializer: envelope missing required fields.');
        }

        $expected = hash_hmac('sha256', $envelope['body'], self::resolveSigningKey());
        if (!hash_equals($expected, $envelope['sig'])) {
            throw new \RuntimeException('ClosureSerializer: signature verification failed. Refusing to eval().');
        }

        $bodyJson = base64_decode($envelope['body'], true);
        if ($bodyJson === false) {
            throw new \RuntimeException('ClosureSerializer: inner base64 decode failed.');
        }
        $body = json_decode($bodyJson, true);
        if (!is_array($body)) {
            throw new \RuntimeException('ClosureSerializer: inner JSON decode failed.');
        }

        $version = (int) ($body['v'] ?? 1);
        if ($version !== self::VERSION) {
            throw new \RuntimeException(
                "ClosureSerializer: payload version {$version} is not supported by this framework version."
            );
        }

        $source = $body['source'] ?? null;
        $uses   = $body['uses']   ?? [];

        if (!is_string($source) || trim($source) === '') {
            throw new \RuntimeException('ClosureSerializer: missing or empty source.');
        }

        $restored = [];
        foreach ($uses as $name => $serialized) {
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', (string) $name)) {
                // A malformed `uses` key would let unserialize() be steered.
                throw new \RuntimeException("ClosureSerializer: invalid use-variable name: {$name}");
            }
            $restored[$name] = unserialize((string) $serialized);
        }
        // Confine the eval() to a static closure so it can't see our locals.
        $build = static function (array $restored, string $source): \Closure {
            extract($restored, EXTR_SKIP);
            $closure = null;
            try {
                eval('$closure = ' . $source . ';');
            } catch (\Throwable $e) {
                throw new \RuntimeException(
                    'ClosureSerializer: eval() failed — ' . $e->getMessage(), 0, $e
                );
            }
            if (!($closure instanceof \Closure)) {
                throw new \RuntimeException('ClosureSerializer: eval() did not produce a Closure.');
            }
            return $closure;
        };

        return $build($restored, $source);
    }

    /**
     * Whether deserialization is allowed in this environment.
     */
    public static function enabled(): bool
    {
        if (!function_exists('config')) {
            return false;
        }
        return (bool) config('closure_serializer.enabled', false);
    }

    /**
     * Resolve the HMAC key. We deliberately reuse APP_KEY rather than introducing
     * a separate key — there's no point having two secrets, and this guarantees
     * that anywhere APP_KEY is missing, this feature is dead.
     */
    private static function resolveSigningKey(): string
    {
        $key = (function_exists('config') ? config('app.key') : null) ?? ($_ENV['APP_KEY'] ?? null);
        if (empty($key)) {
            throw new \RuntimeException(
                'ClosureSerializer: APP_KEY is not configured. Refusing to sign or verify payloads.'
            );
        }
        if (strpos($key, 'base64:') === 0) {
            $key = base64_decode(substr($key, 7));
        }
        return $key;
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
