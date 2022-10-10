<?php

namespace SailCMS\Contracts;

interface AppModule
{
    public function middleware(): void;

    public function cli(): array;
}