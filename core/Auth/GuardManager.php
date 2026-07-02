<?php

namespace Framework\Core\Auth;

use Framework\Core\Container\Container;
use Framework\Core\Http\Request;
use Framework\Core\Http\Session;

/**
 * Resolves named guards from config/auth.php.
 *
 * Managers are lazy — a guard's driver isn't constructed until first use,
 * and the resolved instance is cached for the rest of the request. Every
 * guard sees the SAME Request instance because we resolve it from the
 * container (Kernel::handle() binds it there once per request).
 *
 * Developers can register custom guards at runtime via `extend()`:
 *
 *     Auth::manager()->extend('apikey', fn () => new CallableGuard(...));
 */
class GuardManager
{
    protected Container $container;
    protected array $config;

    /** @var array<string, Guard> Request-lifetime guard cache */
    protected array $resolved = [];

    /** @var array<string, callable> Runtime extensions */
    protected array $extensions = [];

    public function __construct(?Container $container = null, ?array $config = null)
    {
        $this->container = $container ?? Container::getInstance();
        $this->config    = $config ?? $this->loadConfig();
    }

    protected function loadConfig(): array
    {
        $defaults = [
            'default' => 'web',
            'guards'  => [
                'web' => ['driver' => 'session'],
            ],
        ];
        if (!function_exists('config')) {
            return $defaults;
        }
        $cfg = config('auth');
        return is_array($cfg) ? array_replace($defaults, $cfg) : $defaults;
    }

    public function defaultGuard(): string
    {
        return (string) ($this->config['default'] ?? 'web');
    }

    public function guard(?string $name = null): Guard
    {
        $name = $name ?? $this->defaultGuard();
        if (isset($this->resolved[$name])) {
            return $this->resolved[$name];
        }
        return $this->resolved[$name] = $this->resolve($name);
    }

    /**
     * Register a runtime-defined guard. Overrides any same-name entry in
     * config for the current process.
     */
    public function extend(string $name, callable $factory): void
    {
        $this->extensions[$name] = $factory;
        // If the guard was already resolved under this name, drop it so
        // the next call rebuilds from the extension.
        unset($this->resolved[$name]);
    }

    public function reset(): void
    {
        $this->resolved = [];
    }

    protected function resolve(string $name): Guard
    {
        if (isset($this->extensions[$name])) {
            $guard = ($this->extensions[$name])();
            if (!$guard instanceof Guard) {
                throw new \RuntimeException("Guard extension [{$name}] did not return a Guard instance.");
            }
            return $guard;
        }

        $config = $this->config['guards'][$name] ?? null;
        if (!is_array($config)) {
            throw new \InvalidArgumentException("Guard [{$name}] is not configured. See config/auth.php.");
        }

        $driver = $config['driver'] ?? 'session';
        switch ($driver) {
            case 'session':
                return new SessionGuard(
                    $this->makeSession(),
                    (string) ($config['session_key'] ?? "_auth_{$name}")
                );

            case 'jwt':
                return new JwtGuard(
                    $this->currentRequest(),
                    (int) ($config['ttl'] ?? 3600)
                );

            case 'callable':
                if (!isset($config['resolver']) || !is_callable($config['resolver'])) {
                    throw new \InvalidArgumentException(
                        "Guard [{$name}]: 'callable' driver requires a 'resolver' callable."
                    );
                }
                return new CallableGuard(
                    $this->currentRequest(),
                    $config['resolver'],
                    $config['login']  ?? null,
                    $config['logout'] ?? null
                );

            default:
                throw new \InvalidArgumentException("Unknown guard driver [{$driver}] for guard [{$name}].");
        }
    }

    protected function currentRequest(): Request
    {
        try {
            return $this->container->make(Request::class);
        } catch (\Throwable $e) {
            // Fall back to fresh Request when the container isn't populated
            // (typically in CLI / test contexts).
            return new Request();
        }
    }

    protected function makeSession(): Session
    {
        return $this->container->make(Session::class);
    }
}
