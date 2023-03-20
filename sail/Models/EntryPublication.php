<?php

namespace SailCMS\Models;

use MongoDB\BSON\ObjectId;
use SailCMS\Cache;
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
 * @property string $entry_url
 * @property string $user_id
 * @property string $user_full_name
 * @property string $user_email
 *
 * optional
 * @property EntryVersion $version = null
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
    public const FIND_BY_URL_CACHE = 'find_by_url_entry_';   // Add url at the end

    /**
     *
     * Get publication by entry id
     *
     * @param string|ObjectId $entryId
     * @param bool $getVersion
     * @return EntryPublication|null
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function getPublicationByEntryId(string|ObjectId $entryId, bool $getVersion = true, bool $api = true): ?EntryPublication
    {
        if ($api) {
            $this->hasPermissions(true);
        }

        $qs = $this->findOne(['entry_id' => (string)$entryId]);

        if ($getVersion) {
            $qs->populate('entry_version_id', 'version', EntryVersion::class);
        }

        return $qs->exec();
    }

    /**
     *
     * Get a publication by url
     *
     * @param string $url
     * @return EntryPublication|null
     * @throws DatabaseException
     *
     */
    public function getPublicationByUrl(string $url): ?EntryPublication
    {
        $cacheKey = self::FIND_BY_URL_CACHE . $url;
        $cacheTtl = setting('entry.cacheTtl', Cache::TTL_WEEK);

        return $this->findOne(['entry_url' => $url])
            ->populate('entry_version_id', 'version', EntryVersion::class)
            ->exec($cacheKey, $cacheTtl);
    }

    /**
     *
     * @param User $user
     * @param string $entryId
     * @param string $entryUrl
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
    public function create(User $user, string $entryId, string $entryUrl, string $entryVersionId, int $publicationDate = 0, int $expirationDate = 0): string
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
                'entry_id' => $entryId,
                'entry_url' => $entryUrl
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
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    public function deleteAllByEntryId(string $entryId): bool
    {
        $this->hasPermissions();

        try {
            $result = $this->deleteMany(['entry_id' => $entryId]);
        } catch (DatabaseException $exception) {
            $errorMsg = sprintf(self::DATABASE_ERROR[0], 'deleting') . PHP_EOL . $exception->getMessage();
            throw new EntryException($errorMsg, self::DATABASE_ERROR[1]);
        }

        return $result > 0;
    }
}