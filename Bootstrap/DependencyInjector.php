<?php

namespace CandlewaxGames\Bootstrap;

use Exception;
use ReflectionClass;
use ReflectionException;

/**
 * Takes a class name and attempts to recursively resolve all of its dependencies before returning an instance of the
 * class.
 */
class DependencyInjector
{
    /**
     * @var array An array of factory functions for resolving class dependencies that cannot be resolved automatically
     * (e.g a Database class that requires credentials for its constructor params.) Formatted as key = the class name
     * and value = a callable to the factory function.
     */
    private array $dependencies = [];

    /**
     * Registers a factory function for the class defined by $class.
     *
     * @param string $class The name of the class to register.
     * @param callable $factory The factory function.
     * @return void
     */
    public function set(string $class, callable $factory): void
    {
        $this->dependencies[$class] = $factory;
    }

    /**
     * Returns an instance of $class with all non-primitive constructor parameters populated.
     *
     * @param string $class The class of the desired return object.
     * @throws Exception If the given $class constructor has a primitive type parameter that can not be resolved
     * automatically.
     * @throws ReflectionException If the class defined by $class is not Instantiable.
     * @return mixed An instance of $class.
     */
    public function get(string $class): mixed
    {
        // If the requested class has been manually registered, call its factory function
        if (isset($this->dependencies[$class])) {
            return $this->dependencies[$class]($class);
        }

        return $this->resolve($class);
    }

    /**
     * Takes the given class and returns an instance with all dependencies recursively resolved.
     *
     * The dependencies should be defined in the classes constructor parameters.
     *
     * @param string $class The class of the desired return object.
     * @throws ReflectionException If the class defined by $class is not Instantiable.
     * @throws Exception If the given $class constructor has a primitive type parameter that can not be resolved
     * automatically.
     * @return mixed An instance of $class.
     */
    private function resolve(string $class): mixed
    {
        $reflection = new ReflectionClass($class);

        // Check if the class can be instantiated.
        if (!$reflection->isInstantiable()) {
            throw new ReflectionException("Class $class cannot be instantiated.");
        }

        // Get the constructor.
        $constructor = $reflection->getConstructor();

        // If the given class has no constructor, just instantiate the class.
        if (is_null($constructor)) {
            return new $class();
        }

        // Get the parameters (dependencies) from the constructor.
        $parameters = $constructor->getParameters();

        // Loop through each parameter and resolve its type.
        $dependencies = [];
        foreach ($parameters as $parameter) {
            // If the parameter type is a built-in type (not a class, interface or trait) we can not resolve it.
            if ($parameter->getType()->isBuiltin()) {
                throw new Exception("Cannot resolve primitive type for parameter $parameter->name");
            }

            // Resolve the dependency recursively.
            $dependencies[] = $this->get($parameter->getType());
        }

        // Finally, instantiate the class with its dependencies.
        return $reflection->newInstanceArgs($dependencies);
    }
}
