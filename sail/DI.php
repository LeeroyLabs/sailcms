<?php

namespace SailCMS;

use _PHPStan_eb00fd21c\Symfony\Component\String\Exception\RuntimeException;
use ReflectionClass;
use ReflectionException;

class DI
{
    /**
     *
     * Resolve the dependencies for the given class
     *
     * @param  string  $className
     * @return mixed
     *
     */
    public static function resolve(string $className): mixed
    {
        try {
            $class = new ReflectionClass($className);
        } catch (ReflectionException) {
            throw new RuntimeException("Cannot use Dependency Injection on {$className}", 0400);
        }

        try {
            $method = $class->getMethod('__construct');
            $params = $method->getParameters();
            $arguments = [];

            foreach ($params as $param) {
                $typeObj = $param->getType();

                if ($typeObj) {
                    $t = $typeObj->getName();
                    $arguments[] = new $t();
                } else {
                    $arguments[] = null;
                }
            }

            return $class->newInstance(...$arguments);
        } catch (ReflectionException $e) {
            // No construct, no DI
            try {
                return $class->newInstance();
            } catch (ReflectionException $e) {
                throw new \RuntimeException(
                    "Cannot create instance of class {$className}",
                    0400
                );
            }
        }
    }
}