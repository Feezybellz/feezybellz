<?php

namespace Framework\Core\Security;

/**
 * Password hashing helper.
 *
 * Thin wrapper over password_hash / password_verify. Defaults to Argon2id
 * when the runtime supports it, falling back to bcrypt otherwise.
 * Argon2id is preferred because it's memory-hard (resistant to GPU / ASIC
 * attacks) and it's the OWASP-recommended default.
 *
 * Usage:
 *
 *   $hash = Hash::make($plaintextPassword);
 *   if (Hash::check($submitted, $storedHash)) { ... }
 *
 *   if (Hash::needsRehash($storedHash)) {
 *       $storedHash = Hash::make($submitted);
 *       $user->update(['password' => $storedHash]);
 *   }
 *
 * All comparisons are constant-time via password_verify (which internally
 * uses hash_equals).
 */
class Hash
{
    /**
     * Which algorithm to use for new hashes. Reads from config('hashing.driver')
     * when available; falls back to Argon2id if the runtime supports it,
     * bcrypt otherwise.
     */
    protected static function algorithm(): string
    {
        if (function_exists('config')) {
            $configured = config('hashing.driver');
            if (is_string($configured) && $configured !== '') {
                return $configured;
            }
        }
        if (defined('PASSWORD_ARGON2ID')) {
            return PASSWORD_ARGON2ID;
        }
        return PASSWORD_BCRYPT;
    }

    /**
     * Per-algorithm cost/parameter overrides.
     */
    protected static function options(): array
    {
        $cfg = function_exists('config') ? (config('hashing.options') ?? []) : [];
        return is_array($cfg) ? $cfg : [];
    }

    /**
     * Hash a plaintext value. Uses the configured algorithm and options.
     */
    public static function make(string $value): string
    {
        $hash = password_hash($value, self::algorithm(), self::options());
        if ($hash === false) {
            // password_hash() returning false is an OpenSSL-level failure —
            // an integrator problem, not a caller problem.
            throw new \RuntimeException("Hash::make() failed. Check password_hash() runtime support.");
        }
        return $hash;
    }

    /**
     * Constant-time comparison of a plaintext value against an existing hash.
     */
    public static function check(string $value, string $hashed): bool
    {
        if ($hashed === '') {
            return false;
        }
        return password_verify($value, $hashed);
    }

    /**
     * Returns true when the stored hash was generated with weaker
     * parameters than the current configuration would produce. Call this
     * after a successful check() to opportunistically upgrade users to
     * stronger hashes.
     */
    public static function needsRehash(string $hashed): bool
    {
        return password_needs_rehash($hashed, self::algorithm(), self::options());
    }
}
