<?php

namespace SailCMS\Contracts;

use SailCMS\Collection;

interface AppModule
{
    public function middleware(): void;

    public function cli(): Collection;
}