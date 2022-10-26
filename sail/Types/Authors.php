<?php

namespace SailCMS\Types;

class Authors
{
    public function __construct(public readonly ?string $createdBy, public readonly ?string $updatedBy, public readonly ?string $publishedBy, public readonly ?string $deletedBy)
    {
    }

    /**
     * Transform class to an array
     *  TODO maybe extend a type that does that dynamically
     *
     * @return array
     */
    public function toArray():array {
        return [
            'createdBy' => $this->createdBy,
            'updatedBy' => $this->updatedBy,
            'publishedBy' => $this->publishedBy,
            'deletedBy' => $this->deletedBy,
        ];
    }
}