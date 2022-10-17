<?php

namespace SailCMS\Types;

class Username
{
    public function __construct(public readonly string $first, public readonly string $last, public readonly string $full)
    {
    }
}