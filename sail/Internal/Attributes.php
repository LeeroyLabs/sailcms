<?php

namespace SailCMS\Internal;

use ReflectionClass;
use SailCMS\Attributes\GraphQL\CustomResolver;
use SailCMS\Attributes\GraphQL\Mutation;
use SailCMS\Attributes\GraphQL\Query;
use SailCMS\Attributes\GraphQL\Resolver;
use SailCMS\Attributes\Routing\Prefix;
use SailCMS\Attributes\Routing\Route;
use SailCMS\Attributes\Routing\Secure;
use SailCMS\Errors\GraphqlException;
use SailCMS\Errors\StorageException;
use SailCMS\GraphQL;
use SailCMS\Routing\Controller;
use SailCMS\Routing\Router;
use SailCMS\Sail;
use SailCMS\Storage;

class Attributes
{
    private static Router $router;

    /**
     *
     * Parse Attributes within controllers
     *
     * @throws \ReflectionException
     * @throws StorageException
     *
     */
    public static function parseAttributes(): void
    {
        self::$router = new Router();

        $basePath = Sail::getWorkingDirectory() . '/containers';
        $containers = Storage::on('local')->read('composer.json')->decode()->sailcms->containers;
        $list = [];

        foreach ($containers as $container) {
            array_push($list, ...self::getContainerControllers($basePath, $container));
        }

        // Detect all GraphQL controllers
        $files = glob(dirname(__DIR__) . '/GraphQL/Controllers/*.php');
        $gqlList = [];

        foreach ($files as $file) {
            $gqlList[] = 'SailCMS\\GraphQL\\Controllers\\' . str_replace('.php', '', basename($file));
        }

        // Add system classes
        array_push($list, Controller::class, ...$gqlList);

        $gqlTrigger = '/' . setting('graphql.trigger', 'graphql');
        $gqlActive = setting('graphql.active', true);

        foreach ($list as $controller) {
            $reflectionClass = new ReflectionClass($controller);
            $prefix = '';

            // Class level attributes
            foreach ($reflectionClass->getAttributes() as $attribute) {
                if ($attribute->getName() === Prefix::class) {
                    $prefixInstance = $attribute->newInstance();
                    $prefix = $prefixInstance->prefix;

                    // Add missing / in prefix
                    if (!str_starts_with($prefix, '/')) {
                        $prefix = '/' . $prefix;
                    }
                }
            }

            // Method level attributes
            foreach ($reflectionClass->getMethods() as $method) {
                $attributes = $method->getAttributes();
                $isSecure = false;

                foreach ($attributes as $attribute) {
                    if ($attribute->getName() === Secure::class) {
                        $isSecure = true;
                    }
                }

                foreach ($attributes as $attribute) {
                    $item = $attribute->newInstance();

                    // Route Parsing
                    if ($item instanceof Route) {
                        self::addRoute($controller, $method->getName(), $item, $isSecure, $prefix);
                        continue;
                    }

                    // GraphQL Parsing
                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SERVER['REQUEST_URI'] === $gqlTrigger && $gqlActive) {
                        if ($item instanceof Query) {
                            try {
                                GraphQL::addQueryResolver($item->query, $controller, $method->getName());
                            } catch (GraphqlException) {
                            }
                        }

                        if ($item instanceof Mutation) {
                            try {
                                GraphQL::addMutationResolver($item->mutation, $controller, $method->getName());
                            } catch (GraphqlException) {
                            }
                        }

                        if ($item instanceof Resolver) {
                            try {
                                GraphQL::addResolver($item->type, $controller, $method->getName());
                            } catch (GraphqlException) {
                            }
                        }

                        if ($item instanceof CustomResolver) {
                            try {
                                GraphQL::addCustomTypeResolver($item->type, $controller, $method->getName());
                            } catch (GraphqlException) {
                            }
                        }
                    }
                }
            }
        }
    }

    private static function getContainerControllers(string $path, string $container): array
    {
        $output = [];

        if (file_exists($path . '/' . $container)) {
            $controllers = glob($path . '/' . $container . '/controllers/*.php');

            foreach ($controllers as $controller) {
                $output[] = str_replace('.php', '', $container . '\\Controllers\\' . basename($controller));
            }
        }

        return $output;
    }

    private static function addRoute(string $controller, string $method, Route $route, bool $isSecure = false, string $prefix = ''): void
    {
        $routeURL = str_replace('//', '/', $prefix . '/' . $route->route);
        call_user_func([self::$router, $route->http->value], $routeURL, $route->locale, $controller, $method, $route->name, $isSecure);
    }
}