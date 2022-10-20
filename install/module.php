<?php

namespace [NAME];

use SailCMS\Collection;
use \SailCMS\Contracts\AppModule;
use \SailCMS\Types\ModuleInformation;

class Module implements AppModule
{
    public function info(): ModuleInformation
    {
        return new ModuleInformation(name: "[NAME]", description: 'your description here', version: 1.0, semver: '1.0.0');
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