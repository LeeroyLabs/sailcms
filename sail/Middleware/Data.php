<?php

namespace SailCMS\Middleware;

class Data
{
    public function __construct(readonly public string $event, public mixed $data)
    {
    }
}