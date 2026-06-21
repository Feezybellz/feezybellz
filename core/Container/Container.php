<?php

namespace Framework\Core\Container;

use ReflectionClass;
use Exception;

class Container
{
    /**
     * The current globally available container (if any).
     */
    protected static $instance;

    /**
     * The container's bindings.
     */
    protected $bindings = [];

    /**
     * The container's shared (singleton) instances.
     */
    protected $instances = [];

    /**
     * Get the globally available instance of the container.
     */
    public static function getInstance(): self
    {
        if (is_null(static::$instance)) {
            static::$instance = new static;
        }

        return static::$instance;
    }

    /**
     * Set the shared instance of the container.
     */
    public static function setInstance(?Container $container = null): ?Container
    {
        return static::$instance = $container;
    }

    /**
     * Register a binding with the container.
     */
    public function bind(string $abstract, $concrete = null, bool $shared = false): void
    {
        if (is_null($concrete)) {
            $concrete = $abstract;
        }
        
        $this->bindings[$abstract] = compact('concrete', 'shared');
    }

    /**
     * Register a shared (singleton) binding in the container.
     */
    public function singleton(string $abstract, $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    /**
     * Register an existing instance as shared in the container.
     */
    public function instance(string $abstract, $instance)
    {
        $this->instances[$abstract] = $instance;

        return $instance;
    }

    /**
     * Resolve the given type from the container.
     */
    public function make(string $abstract, array $parameters = [])
    {
        // 1. If it's a singleton and we already made it, return the instance.
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        $concrete = $this->bindings[$abstract]['concrete'] ?? $abstract;

        // 2. If the concrete is a Closure, execute it to build the object.
        if ($concrete instanceof \Closure) {
            $object = $concrete($this, $parameters);
        } else {
            // 3. Otherwise, use Reflection to auto-wire and build the object.
            $object = $this->build($concrete, $parameters);
        }

        // 4. If it was registered as a singleton, cache the instance for next time.
        if (isset($this->bindings[$abstract]['shared']) && $this->bindings[$abstract]['shared']) {
            $this->instances[$abstract] = $object;
        }

        return $object;
    }

    protected $reflectionCache = [];

    /**
     * Instantiate a concrete instance and auto-inject its dependencies.
     */
    protected function build(string $concrete, array $parameters = [])
    {
        if (!isset($this->reflectionCache[$concrete])) {
            try {
                $reflector = new ReflectionClass($concrete);
            } catch (\ReflectionException $e) {
                throw new Exception("Target class [$concrete] does not exist.", 0, $e);
            }

            $constructor = $reflector->getConstructor();
            $this->reflectionCache[$concrete] = [
                'reflector' => $reflector,
                'constructor' => $constructor,
                'params' => $constructor ? $constructor->getParameters() : [],
            ];
        }

        $cached = $this->reflectionCache[$concrete];

        if (!$cached['reflector']->isInstantiable()) {
            throw new Exception("Target [$concrete] is not instantiable.");
        }

        if (is_null($cached['constructor'])) {
            return new $concrete;
        }

        $instances = $this->resolveDependencies($cached['params'], $parameters);

        return $cached['reflector']->newInstanceArgs($instances);
    }

    /**
     * Resolve all of the dependencies from the ReflectionParameters.
     */
    protected function resolveDependencies(array $dependencies, array $parameters): array
    {
        $results = [];

        foreach ($dependencies as $dependency) {
            $name = $dependency->getName();
            $type = $dependency->getType();

            // If a parameter was explicitly passed to make(), use it.
            if (array_key_exists($name, $parameters)) {
                $results[] = $parameters[$name];
                continue;
            }

            // If the dependency is a built-in type (string, int) without a default value, we can't resolve it.
            if (!$type || $type->isBuiltin()) {
                if ($dependency->isDefaultValueAvailable()) {
                    $results[] = $dependency->getDefaultValue();
                } else {
                    throw new Exception("Unresolvable dependency resolving [$name] in class {$dependency->getDeclaringClass()->getName()}");
                }
            } else {
                // RECURSION: The parameter is a class! Ask the container to resolve IT!
                $results[] = $this->make($type->getName());
            }
        }

        return $results;
    }
}
