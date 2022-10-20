<?php

namespace SailCMS\Types;

class ContainerInformation
{
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly float $version,
        public readonly string $semver
    ) {
    }
}