<?php

namespace SailCMS;

use SailCMS\Collection;
use SailCMS\Types\ContainerInformation;
use SailCMS\Models\Containers;

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
     * @param ContainerInformation $info
     * @param string $className
     * @return void
     *
     * @throws Core\Errors\DatabaseException
     *
     */
    public function registerContainer(ContainerInformation $info, string $className): void
    {
        static::$containers->push((object)[
            'info' => $info,
            'class' => $className
        ]);

        $containerModel = new Containers();
        $containerModel->register(className: $className, info: $info);
    }
}