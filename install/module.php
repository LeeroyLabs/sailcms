<?php

namespace [NAME];

use SailCMS\Collection;
use \SailCMS\Contracts\AppModule;
use \SailCMS\Types\ModuleInformation;

class Module implements AppModule
{
    public function info(): ModuleInformation
    {
        return new ModuleInformation('[NAME]', 'your description here', 1.0, '1.0.0');
    }

    public function cli(): Collection
    {
        return Collection::init();
    }

    public function middleware(): void
    {
    }

    public function events(): void
    {
        // register for events
    }

    // Your code
}