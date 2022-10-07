<?php

namespace SailCMS\Contracts;

abstract class AppModule
{
    abstract public function middleware(): void;
    
    public function __construct()
    {
        // TODO: IMPLEMENT WHATEVER
    }
}