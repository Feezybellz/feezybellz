<?php

namespace Framework\Core\Http;

interface SessionDriverInterface
{
    public function start(): bool;
    public function get(string $key, $default = null);
    public function set(string $key, $value): void;
    public function has(string $key): bool;
    public function remove(string $key): void;
    public function clear(): void;
    public function flash(string $key, $value): void;
    public function getFlash(string $key, $default = null);
    public function regenerate(): bool;
    public function id(): string;
}
