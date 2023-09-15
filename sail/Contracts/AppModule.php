<?php

namespace SailCMS\Contracts;

use SailCMS\Collection;
use SailCMS\Types\ModuleInformation;

interface AppModule
{
    public function init(): void;
    
    public function info(): ModuleInformation;

    public function middleware(): void;

    public function cli(): Collection;

    public function events(): void;
}