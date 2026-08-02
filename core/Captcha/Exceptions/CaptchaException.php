<?php

namespace Framework\Core\Captcha\Exceptions;

use RuntimeException;

class CaptchaException extends RuntimeException
{
    public static function expiredOrInvalid(): self
    {
        return new self('The security challenge token is invalid, corrupted, or has expired.');
    }

    public static function submissionTooFast(int $minSeconds): self
    {
        return new self("Form submitted unrealistically fast under the minimum human latency threshold ({$minSeconds}s). Automated spam rejected.");
    }

    public static function invalidProofOfWork(): self
    {
        return new self('The submitted Proof-of-Work computation solution is incorrect or does not satisfy the target difficulty.');
    }

    public static function replayDetected(): self
    {
        return new self('Replay attack detected: This challenge token and nonce have already been consumed in a previous submission.');
    }

    public static function missingBehavioralEntropy(): self
    {
        return new self('Automated scraper detected: No passive human interaction events (focus, pointer move, keyboard, touch) were registered during form completion.');
    }

    public static function nameMismatch(string $expected, string $actual): self
    {
        return new self("Security challenge scope mismatch: expected form name [{$expected}] but received challenge for [{$actual}].");
    }

    public static function invalidPuzzleAlignment(): self
    {
        return new self("Image puzzle challenge alignment incorrect. The puzzle piece was not placed accurately in the slot.");
    }
}
