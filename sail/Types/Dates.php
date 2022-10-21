<?php

namespace SailCMS\Types;

class Dates
{
    public function __construct(public readonly ?int $created, public readonly ?int $updated, public readonly ?int $published, public readonly ?int $deleted)
    {
    }
}