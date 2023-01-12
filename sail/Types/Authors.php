<?php

namespace SailCMS\Types;

use JetBrains\PhpStorm\Pure;
use SailCMS\Contracts\Castable;
use SailCMS\Contracts\DatabaseType;
use SailCMS\Models\User;

readonly class Authors implements Castable
{
    public function __construct(
        public ?string $created_by = '',
        public ?string $updated_by = '',
        public ?string $published_by = '',
        public ?string $deleted_by = ''
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
    public static function init(User $author, bool $published): array
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
    public static function updated(Authors $authors, string $updateAuthorId)
    {
        $newAuthors = new Authors($authors->created_by, $updateAuthorId, $authors->published_by, $authors->deleted_by);

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
    public static function deleted(Authors $authors, string $deleteAuthorId): array
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

    /**
     *
     * Cast to simpler format from Authors
     *
     * @return array
     *
     */
    public function castFrom(): array
    {
        return [
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'published_by' => $this->published_by ?? '',
            'deleted_by' => $this->deleted_by ?? '',
        ];
    }

    /**
     *
     * Cast to Authors
     *
     * @param  mixed  $value
     * @return Authors
     *
     */
    public function castTo(mixed $value): Authors
    {
        return new self($value->created_by, $value->updated_by, $value->published_by, $value->deleted_by);
    }
}