<?php

namespace Tests\Fixtures;

/**
 * A minimal event fixture with JSON-safe public properties.
 */
class FakeUserRegistered
{
    public $userId;
    public $email;

    public function __construct(int $userId, string $email)
    {
        $this->userId = $userId;
        $this->email = $email;
    }
}
