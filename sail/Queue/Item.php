<?php

namespace SailCMS\Queue;

use SailCMS\Collection;

class Item
{
    public const PRIORITY_NORMAL = 3;
    public const PRIORITY_HIGH = 2;
    public const PRIORITY_URGENT = 1;

    public function __construct(
        public readonly string $name,
        public readonly string $handler,
        public readonly string $action,
        public readonly Collection $settings,
        public readonly int $priority = Item::PRIORITY_NORMAL
    ) {
    }
}