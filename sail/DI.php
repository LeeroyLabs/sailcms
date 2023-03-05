<?php

namespace SailCMS;

use ReflectionClass;
use ReflectionException;
use \RuntimeException;

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

                    $base = ['array', 'string', 'bool', 'int', 'float', 'resource'];

                    if (!in_array($t, $base)) {
                        $arguments[] = new $t();
                    } else {
                        switch ($t) {
                            case 'array':
                                $arguments[] = [];
                                break;

                            case 'string':
                                $arguments[] = '';
                                break;

                            case 'int':
                            case 'float':
                                $arguments = 0;
                                break;

                            case 'bool':
                                $arguments = false;
                                break;

                            case 'resource':
                                $arguments = null;
                                break;
                        }
                    }
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