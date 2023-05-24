<?php

namespace SailCMS\Types;

class AssetConfig
{
    public function __construct(public readonly int $maxSize, public readonly array $blacklist)
    {
    }
}