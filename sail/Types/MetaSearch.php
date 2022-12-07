<?php

namespace SailCMS\Types;

class MetaSearch
{
    public function __construct(public readonly string $key, public readonly string $value) { }
}