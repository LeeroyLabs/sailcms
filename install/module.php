<?php

namespace [NAME];

use SailCMS\Collection;
use \SailCMS\Contracts\AppModule;
use \SailCMS\Types\ModuleInformation;

class Module implements AppModule
{
    public function info(): ModuleInformation
    {
        return new ModuleInformation("[NAME]", '*', 1.0, '1.0.0');
    }

    public function cli(): Collection
    {
        return [];
    }

    public function middleware(): void
    {
    }

    // Your code
}