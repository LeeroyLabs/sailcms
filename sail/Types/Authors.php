<?php

namespace SailCMS\Types;

class Authors
{
    public function __construct(public readonly ?string $createdBy, public readonly ?string $updatedBy, public readonly ?string $publishedBy, public readonly ?string $deletedBy)
    {
    }
}