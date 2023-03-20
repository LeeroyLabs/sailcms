<?php

namespace SailCMS\Types;

class ModuleInformation
{
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly float $version,
        public readonly string $semver,
        public readonly string $author = 'Unknown',
        public readonly string $link = ''
    ) {
    }
}