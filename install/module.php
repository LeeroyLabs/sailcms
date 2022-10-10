<?php

namespace [NAME];

use \SailCMS\Contracts\AppModule;
use \SailCMS\Types\ModuleInformation;

class Module implements AppModule
{
    public function info(): ModuleInformation
    {
        return new ModuleInformation("[NAME]", '*', 1.0, '1.0.0');
    }

    public function cli(): array
    {
        return [];
    }

    // Your code
    public function middleware(): void
    {
    }
}