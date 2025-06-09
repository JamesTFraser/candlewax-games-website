<?php

namespace CandlewaxGames\Services;

use ReflectionException;
use ReflectionMethod;
use ReflectionParameter;

class ParamResolver
{
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
            unset($values[$param->name]);
            return $value;
        }

        // If the $values array does not contain parameter names.
        if (!$isAssoc && isset($values[0])) {
            // Remove the first value from the $values array and add it to the end of the $orderedParams array.
            return array_shift($values);
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
}
