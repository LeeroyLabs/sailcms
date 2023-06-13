<?php

namespace SailCMS\Types;

class FieldInfo
{
    public function __construct(
        public readonly string      $name,
        public readonly string      $fullname,
        public readonly string      $handle,
        public readonly LocaleField $description,
        public readonly string      $category,
        public readonly string      $storingType,
        public readonly bool        $searchable,
        public readonly bool        $repeatable,
        public readonly array       $inputs
    )
    {
    }
}