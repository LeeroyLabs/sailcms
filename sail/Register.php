<?php

namespace SailCMS;

use SailCMS\Contracts\AppContainer;
use SailCMS\Contracts\AppMiddleware;
use SailCMS\Contracts\AppModule;
use SailCMS\Errors\RegisterException;
use SailCMS\Types\ContainerInformation;
use SailCMS\Types\ModuleInformation;

class Register
{
    private static Collection $containers;
    private static Collection $modules;
    private static Collection $middlewares;
    private static Register $instance;

    public static function instance(): Register
    {
        if (empty(static::$instance)) {
            static::$instance = new self();

            static::$containers = new Collection([]);
            static::$modules = new Collection([]);
            static::$middlewares = new Collection([]);
        }

        return static::$instance;
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
        static::$containers->push((object)[
            'name' => $info->name,
            'info' => $info,
            'class' => $className,
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
        static::$modules->push((object)[
            'info' => $info,
            'instance' => $instance,
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
        $container = static::$containers->find(fn($c) => $c->class === $class);

        if ($container) {
            $container->routes[strtolower($method)][] = $url;
        }
    }

    public static function registerMiddleware(AppMiddleware $middleware): void
    {
        //$middleware->type();
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
        $module = static::$modules->find(fn($k, $n) => $n['name'] === $name);

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
        $container = static::$containers->find(fn($k, $n) => $n['name'] === $name);

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
        return static::$modules;
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
        return static::$containers;
    }
}