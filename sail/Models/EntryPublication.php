<?php

namespace SailCMS\Models;

use MongoDB\BSON\ObjectId;
use SailCMS\Collection;
use SailCMS\Database\Model;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\EntryException;
use SailCMS\Errors\PermissionException;
use SailCMS\Types\PublicationDates;

/**
 *
 * @property string $entry_id
 * @property string $entry_version_id
 * @property PublicationDates $dates
 * @property string $user_id
 * @property string $user_full_name
 * @property string $user_email
 *
 */
class EntryPublication extends Model
{
    protected string $collection = 'entry_publications';
    protected string $permissionGroup = 'entrypublication';
    protected array $casting = [
        "entry" => Collection::class,
        "dates" => PublicationDates::class
    ];

    public const DATABASE_ERROR = ['5300: Exception when "%s" an entry publication.', 5300];
    public const EXPIRATION_DATE_ERROR = ['5301: The expiration date must be higher than the publication date', 5301];

    /**
     *
     * @param string|ObjectId $entryId
     * @return array
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function getPublicationsByEntryId(string|ObjectId $entryId): array
    {
        $this->hasPermissions(true);

        return $this->find(['entry_id' => (string)$entryId])->exec();
    }

    /**
     *
     * @param User $user
     * @param string $entryId
     * @param string $entryVersionId
     * @param int $publicationDate
     * @param int $expirationDate
     * @return string
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    public function create(User $user, string $entryId, string $entryVersionId, int $publicationDate = 0, int $expirationDate = 0): string
    {
        $this->hasPermissions();

        $this->deleteAllByEntryId($entryId);

        if ($publicationDate === 0) {
            $publicationDate = time();
        }

        $dates = new PublicationDates($publicationDate, $expirationDate);

        try {
            $entryPublicationId = $this->insert([
                'dates' => $dates,
                'user_id' => (string)$user->_id,
                'user_full_name' => $user->name->full,
                'user_email' => $user->email,
                'entry_version_id' => $entryVersionId,
                'entry_id' => $entryId
            ]);
        } catch (DatabaseException $exception) {
            $errorMsg = sprintf(self::DATABASE_ERROR[0], 'creating') . PHP_EOL . $exception->getMessage();
            throw new EntryException($errorMsg, self::DATABASE_ERROR[1]);
        }

        return (string)$entryPublicationId;
    }

    /**
     *
     * Delete publications for a given entry id
     *
     * @param string $entryId
     * @return bool
     * @throws EntryException
     *
     */
    public function deleteAllByEntryId(string $entryId): bool
    {
        try {
            $result = $this->deleteMany(['entry_id' => $entryId]);
        } catch (DatabaseException $exception) {
            $errorMsg = sprintf(self::DATABASE_ERROR[0], 'deleting') . PHP_EOL . $exception->getMessage();
            throw new EntryException($errorMsg, self::DATABASE_ERROR[1]);
        }

        return $result > 0;
    }
}