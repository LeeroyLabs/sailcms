<?php

namespace SailCMS\Types;

use SailCMS\Contracts\DatabaseType;
use SailCMS\Models\User;

class Authors implements DatabaseType
{
    public function __construct(
        public readonly ?string $createdBy,
        public readonly ?string $updatedBy,
        public readonly ?string $publishedBy,
        public readonly ?string $deletedBy
    ) {
    }

    /**
     *
     * Init an authors type with now
     *
     * @param  User  $author
     * @param  bool  $published
     * @return array
     *
     */
    static public function init(User $author, bool $published): array
    {
        $publisherId = null;
        if ($published) {
            $publisherId = $author->_id;
        }

        $authors = new Authors($author->_id, $author->_id, $publisherId, null);
        return $authors->toDBObject();
    }

    /**
     *
     * Update the updateBy attribute of a given Authors object
     *
     * @param  Authors  $authors
     * @param  string   $updateAuthorId
     * @return array
     */
    static public function updated(Authors $authors, string $updateAuthorId)
    {
        $newAuthors = new Authors($authors->createdBy, $updateAuthorId, $authors->publishedBy, $authors->deletedBy);

        return $newAuthors->toDBObject();
    }

    /**
     *
     * Update the deletedBy attribute of a given Authors object
     *
     * @param  Authors  $authors
     * @param  string   $deleteAuthorId
     * @return array
     *
     */
    static public function deleted(Authors $authors, string $deleteAuthorId): array
    {
        $newAuthors = new Authors($authors->createdBy, $authors->updatedBy, $authors->publishedBy, $deleteAuthorId);

        return $newAuthors->toDBObject();
    }

    /**
     *
     * Transform class to an array
     *
     * @return array
     *
     */
    public function toDBObject(): array
    {
        return [
            'created_by' => $this->createdBy,
            'updated_by' => $this->updatedBy,
            'published_by' => $this->publishedBy,
            'deleted_by' => $this->deletedBy,
        ];
    }
}