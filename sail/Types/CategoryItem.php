<?php

namespace SailCMS\Types;

use SailCMS\Collection;

class CategoryItem
{
    public function __construct(
        public readonly string $_id,
        public readonly LocaleField $name,
        public readonly string $slug,
        public readonly string $parent_id,
        public readonly int $order,
        public readonly Collection $children
    ) {
    }
}