<?php

namespace SailCMS;

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
            'info' => $info,
            'class' => $className
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
     * Get a loaded module
     *
     * @param  string  $name
     * @return AppModule
     * @throws RegisterException
     *
     */
    public static function getModule(string $name): AppModule
    {
        $module = static::$modules->find(fn($n) => $n['name'] === $name);

        if (!empty($module)) {
            return $module->instance;
        }

        throw new RegisterException("Module {$name} does not exist in the register. Please make sure you have the right name.", 0404);
    }

    public function getContainersList(): Collection
    {
        $list = new Collection([]);
        $fs = Filesystem::manager();

        $files = $fs->listContents('root://containers', true);

        print_r($files);
    }
}