<?php

namespace SailCMS\Types;

use SailCMS\Models\User;

class Authors
{
    public function __construct(public readonly ?string $createdBy, public readonly ?string $updatedBy, public readonly ?string $publishedBy, public readonly ?string $deletedBy)
    {
    }

    /**
     *
     * @param  User  $author
     * @param  bool  $published
     * @return array
     *
     */
    static public function atCreation(User $author, bool $published): array
    {
        $publisherId = null;
        if ($published) {
            $publisherId = $author->_id;
        }

        $authors = new Authors($author->_id, $author->_id, $publisherId, null);
        return $authors->toArray();
    }

    /**
     *
     * Transform class to an array
     *  TODO maybe extend a type that does that dynamically
     *
     * @return array
     *
     */
    public function toArray(): array
    {
        return [
            'createdBy' => $this->createdBy,
            'updatedBy' => $this->updatedBy,
            'publishedBy' => $this->publishedBy,
            'deletedBy' => $this->deletedBy,
        ];
    }
}