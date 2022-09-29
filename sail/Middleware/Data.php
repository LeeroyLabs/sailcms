<?php

namespace SailCMS\Middleware;

class Data
{
    readonly public string $event;
    public mixed $data;

    public function __construct(string $event, mixed $data)
    {
        $this->event = $event;
        $this->data = $data;
    }
}