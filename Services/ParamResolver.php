<?php

namespace CandlewaxGames\Services;

use ReflectionException;
use ReflectionMethod;
use ReflectionParameter;

class ParamResolver
{
    /** @var array An array matching the return types of ReflectionType::getName() with the types returned from
     * gettype(), where the former is the key and the latter is the value.
     */
    private array $typeLookUp = ['string' => 'string', 'int' => 'integer', 'float' => 'double', 'bool' => 'boolean'];

    /**
     * Attempts to typecast a string into the variable type it represents.
     *
     * @param string $value The string to attempt to typecast the value of.
     * @return mixed The typecast $value.
     */
    public function typeCastFromString(string $value): mixed
    {
        // If the value is numeric.
        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float)$value : (int)$value;
        }

        $lower = strtolower($value);
        if ($lower === 'true') {
            return true;
        }
        if ($lower === 'false') {
            return false;
        }
        if ($lower === 'null') {
            return null;
        }

        return $value;
    }

    /**
     * Attempts to match the data stored in the $values array to the parameters of the given method.
     *
     * If successful, an array of data ordered by the position of the method parameters is returned. If
     * a corresponding value cannot be found in the $values array for a given $action parameter and the parameter is
     * not optional, then null is returned.
     *
     * @param string $controller The class where the $action method resides.
     * @param string $action The method whose parameters need resolving.
     * @param array $values An array of values used to supply the $action method with.
     * @return array|null An array of values ordered corresponding to the $action's parameters or null.
     * @throws ReflectionException If the method specified by the $controller and $action parameters cannot be found.
     */
    public function resolveMethodParams(string $controller, string $action, array $values): ?array
    {
        // Retrieve the action's parameters.
        $reflection = new ReflectionMethod($controller, $action);
        $params = $reflection->getParameters();

        // Go through each parameter and attempt to supply it with the value it requires.
        $orderedParams = [];
        foreach ($params as $param) {
            // Construct an array of callables to loop through to find a value for the parameter.
            $resolvers = [
                fn() => $this->resolveParamByArray($param, $values),
                fn() => $this->resolvePost($param),
                fn() => $this->resolveSession($param),
                fn() => $this->resolveFiles($param),
                fn() => $this->resolveDefault($param)
            ];

            // Loop through the resolver methods to find a value for the parameter.
            foreach ($resolvers as $resolver) {
                $value = $resolver();

                // If a value has been found, exit the current loop and move on to the next $param.
                if ($value !== null) {
                    $orderedParams[] = $value;
                    continue 2;
                }
            }

            // If no matching value can be found for the current $param, stop iterating and return null.
            return null;
        }
        return $orderedParams;
    }

    private function resolveParamByArray(ReflectionParameter $param, array &$values): mixed
    {
        // Check if the $values array is associative.
        $isAssoc = !array_is_list($values);

        // If the parameter required by the action is present in the url, add it to the array.
        if ($isAssoc && array_key_exists($param->name, $values)) {
            $value = $values[$param->name];

            // Check the value's type matches the parameter type and return it if so.
            if ($this->matchType($param, gettype($value))) {
                unset($values[$param->name]);
                return $value;
            }

            // If the value from the url does not match the parameter's type.
            return null;
        }

        // If the $values array does not contain parameter names.
        if (!$isAssoc && isset($values[0])) {
            // Return the value if it's type matches the parameter's required type and remove it from the $values array.
            return $this->matchType($param, gettype($values[0])) ? array_shift($values) : null;
        }

        // The parameter could not be satisfied.
        return null;
    }

    private function resolvePost(ReflectionParameter $param): ?array
    {
        // If the current parameter is named, post.
        if ($param->name == 'post' && !empty($_POST)) {
            return $_POST;
        }

        // The parameter could not be satisfied.
        return null;
    }

    private function resolveSession(ReflectionParameter $param): ?array
    {
        // If the current parameter is named, session.
        if ($param->name == 'session' && !empty($_SESSION)) {
            return $_SESSION;
        }

        // The parameter could not be satisfied.
        return null;
    }

    private function resolveFiles(ReflectionParameter $param): ?array
    {
        // If the current parameter is named, files.
        if ($param->name == 'files' && !empty($_FILES)) {
            return $_FILES;
        }

        // The parameter could not be satisfied.
        return null;
    }

    private function resolveDefault(ReflectionParameter $param): mixed
    {
        if ($param->isDefaultValueAvailable()) {
            return $param->getDefaultValue();
        }

        // The parameter could not be satisfied.
        return null;
    }

    private function matchType(ReflectionParameter $parameter, string $matchType): bool
    {
        $paramType = $this->typeLookUp[$parameter->getType()->getName()];
        return strtolower($paramType) === $matchType;
    }
}
