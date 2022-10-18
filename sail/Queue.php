<?php

namespace SailCMS;

class Queue
{
    private static Queue $manager;

    private function __construct()
    {
    }

    public function manager(): Queue
    {
        if (!isset(static::$manager)) {
            static::$manager = new static();
        }

        return static::$manager;
    }
}