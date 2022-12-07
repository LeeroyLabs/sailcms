<?php

namespace SailCMS\Types;

class UserTypeSearch
{
    public function __construct(public readonly string $type, public readonly bool $except) { }
}