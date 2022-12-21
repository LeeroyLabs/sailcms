<?php

namespace SailCMS;

use SailCMS\Contracts\AppContainer;
use SailCMS\Contracts\AppMiddleware;
use SailCMS\Contracts\AppModule;
use SailCMS\Errors\RegisterException;
use SailCMS\Types\ContainerInformation;
use SailCMS\Types\ModuleInformation;

final class Register
{
    private static Collection $containers;
    private static Collection $modules;
    private static Register $instance;

    public static function instance(): Register
    {
        if (empty(self::$instance)) {
            self::$instance = new self();

            self::$containers = Collection::init();
            self::$modules = Collection::init();
        }

        return self::$instance;
    }

    /**
     *
     * Register a container for later use
     *
     * @param  ContainerInformation  $info
     * @param  string                $className
     * @return void
     *
     */
    public function registerContainer(ContainerInformation $info, string $className): void
    {
        self::$containers->push((object)[
            'name' => $info->name,
            'info' => $info,
            'class' => $className,
            'middlewares' => [],
            'graphql' => [
                'queries' => [],
                'mutations' => [],
                'resolvers' => []
            ],
            'routes' => [
                'post' => [],
                'get' => [],
                'put' => [],
                'any' => [],
                'delete' => [],
                'redirect' => []
            ]
        ]);
    }

    /**
     *
     * Register a module for later use
     *
     * @param  ModuleInformation  $info
     * @param  AppModule          $instance
     * @param  string             $moduleName
     * @return void
     *
     */
    public function registerModule(ModuleInformation $info, AppModule $instance, string $moduleName): void
    {
        self::$modules->push((object)[
            'info' => $info,
            'instance' => $instance,
            'class' => get_class($instance),
            'middlewares' => [],
            'name' => $moduleName
        ]);
    }

    /**
     *
     * Register a route to the generating container
     *
     * @param  string  $method
     * @param  string  $url
     * @param  string  $class
     * @return void
     *
     */
    public static function registerRoute(string $method, string $url, string $class): void
    {
        $container = self::$containers->find(fn($k, $c) => $c->class === $class);

        if ($container) {
            $container->routes[strtolower($method)][] = $url;
        }
    }

    /**
     *
     * Register a middleware to the generating container or module
     *
     * @param  AppMiddleware  $middleware
     * @param  string         $containerOrModule
     * @return void
     *
     */
    public static function registerMiddleware(AppMiddleware $middleware, string $containerOrModule): void
    {
        $type = $middleware->type();
        $containerObj = self::$containers->find(fn($k, $c) => $c->class === $containerOrModule);

        if ($containerObj && is_subclass_of($containerObj->class, AppContainer::class)) {
            $containerObj->middlewares[] = (object)[
                'type' => $type,
                'name' => get_class($middleware)
            ];
        } else {
            $module = self::$modules->find(fn($k, $c) => $c->class === $containerOrModule);

            if ($module) {
                $module->middlewares[] = (object)[
                    'type' => $type,
                    'name' => get_class($middleware)
                ];
            }
        }
    }

    /**
     *
     * Register a graphQL Query
     *
     * @param  string  $name
     * @param  string  $handler
     * @param  string  $method
     * @param  string  $container
     * @return void
     *
     */
    public static function registerGraphQLQuery(string $name, string $handler, string $method, string $container): void
    {
        $container = self::$containers->find(fn($k, $c) => $c->class === $container);

        if ($container) {
            $container->graphql['queries'][] = (object)[
                'operation' => $name,
                'handler' => $handler,
                'method' => $method
            ];
        }
    }

    /**
     *
     * Register a GraphQL Mutation
     *
     * @param  string  $name
     * @param  string  $handler
     * @param  string  $method
     * @param  string  $container
     * @return void
     *
     */
    public static function registerGraphQLMutation(string $name, string $handler, string $method, string $container): void
    {
        $container = self::$containers->find(fn($k, $c) => $c->class === $container);

        if ($container) {
            $container->graphql['mutations'][] = (object)[
                'operation' => $name,
                'handler' => $handler,
                'method' => $method
            ];
        }
    }

    /**
     *
     * Register a GraphQL Resolver
     *
     * @param  string  $name
     * @param  string  $handler
     * @param  string  $method
     * @param  string  $container
     * @return void
     *
     */
    public static function registerGraphQLResolver(string $name, string $handler, string $method, string $container): void
    {
        $container = self::$containers->find(fn($k, $c) => $c->class === $container);

        if ($container) {
            $container->graphql['resolvers'][] = (object)[
                'operation' => $name,
                'handler' => $handler,
                'method' => $method
            ];
        }
    }

    /**
     *
     * Get a loaded module
     *
     * @param  string  $name
     * @return AppModule
     * @throws RegisterException
     *
     */
    public static function module(string $name): AppModule
    {
        $module = self::$modules->find(fn($k, $n) => $n->name === $name);

        if (!empty($module)) {
            return $module->instance;
        }

        throw new RegisterException("Module {$name} does not exist in the register. Please make sure you have the right name.", 0404);
    }

    /**
     *
     * Get a loaded container
     *
     * @param  string  $name
     * @return AppContainer
     * @throws RegisterException
     *
     */
    public static function container(string $name): AppContainer
    {
        $container = self::$containers->find(fn($k, $n) => $n->name === $name);

        if (!empty($container)) {
            return $container->class();
        }

        throw new RegisterException("Container {$name} does not exist in the register. Please make sure you have the right name.", 0404);
    }

    /**
     *
     * Get all loaded modules
     *
     * @return Collection
     *
     */
    public static function getModules(): Collection
    {
        return self::$modules;
    }

    /**
     *
     * Get all loaded containers
     *
     * @return Collection
     *
     */
    public static function getContainerList(): Collection
    {
        return self::$containers;
    }

    /**
     *
     * Check if a container exists in the current state
     *
     * @param  string  $name
     * @return bool
     *
     */
    public static function containerExists(string $name): bool
    {
        $exists = false;

        self::$containers->each(function ($k, $container) use (&$exists, $name)
        {
            if ($container->info->name === $name) {
                $exists = true;
            }
        });

        return $exists;
    }

    /**
     *
     * Check if a module exists in the current state
     *
     * @param  string  $name
     * @return bool
     *
     */
    public static function moduleExists(string $name): bool
    {
        $exists = false;

        self::$modules->each(function ($k, $module) use (&$exists, $name)
        {
            if ($module->info->name === $name) {
                $exists = true;
            }
        });

        return $exists;
    }
}