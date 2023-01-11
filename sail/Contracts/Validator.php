<?php

namespace SailCMS\Contracts;

interface Validator
{
    public static function validate(string $key, mixed $value): void;
}