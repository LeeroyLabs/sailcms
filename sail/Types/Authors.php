<?php

namespace SailCMS\Types;

use JetBrains\PhpStorm\Pure;
use SailCMS\Contracts\DatabaseType;
use SailCMS\Models\User;

class Authors implements DatabaseType
{
    public function __construct(
        public readonly ?string $created_by,
        public readonly ?string $updated_by,
        public readonly ?string $published_by,
        public readonly ?string $deleted_by
    )
    {
    }

    /**
     *
     * Init an authors type with now
     *
     * @param User $author
     * @param bool $published
     * @return array
     *
     */
    #[Pure] static public function init(User $author, bool $published): array
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
     * @param Authors $authors
     * @param string $updateAuthorId
     * @return array
     */
    static public function updated(Authors $authors, string $updateAuthorId)
    {
        $newAuthors = new Authors($authors->created_by, $updateAuthorId, $authors->published_by, $authors->deleted_by);

        return $newAuthors->toDBObject();
    }

    /**
     *
     * Update the deletedBy attribute of a given Authors object
     *
     * @param Authors $authors
     * @param string $deleteAuthorId
     * @return array
     *
     */
    static public function deleted(Authors $authors, string $deleteAuthorId): array
    {
        $newAuthors = new Authors($authors->created_by, $authors->updated_by, $authors->published_by, $deleteAuthorId);

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
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'published_by' => $this->published_by ?? '',
            'deleted_by' => $this->deleted_by ?? '',
        ];
    }
}