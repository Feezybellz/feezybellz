<?php

namespace Framework\Core\Http;

class Session
{
    protected SessionDriverInterface $driver;

    public function __construct(SessionDriverInterface $driver)
    {
        $this->driver = $driver;
        $this->driver->start();
    }

    public function get(string $key, $default = null)
    {
        return $this->driver->get($key, $default);
    }

    public function set(string $key, $value): void
    {
        $this->driver->set($key, $value);
    }

    public function has(string $key): bool
    {
        return $this->driver->has($key);
    }

    public function remove(string $key): void
    {
        $this->driver->remove($key);
    }

    public function clear(): void
    {
        $this->driver->clear();
    }

    public function flash(string $key, $value): void
    {
        $this->driver->flash($key, $value);
    }

    public function getFlash(string $key, $default = null)
    {
        return $this->driver->getFlash($key, $default);
    }

    public function regenerate(): bool
    {
        return $this->driver->regenerate();
    }

    public function id(): string
    {
        return $this->driver->id();
    }
}
