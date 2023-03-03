<?php

namespace SailCMS\Models;

use JsonException;
use League\Flysystem\FilesystemException;
use MongoDB\BSON\ObjectId;
use SailCMS\Collection;
use SailCMS\Database\Model;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\EntryException;
use SailCMS\Errors\PermissionException;
use SailCMS\Types\QueryOptions;
use SodiumException;

/**
 *
 * @property int $created_at
 * @property string $user_id
 * @property string $user_full_name
 * @property string $user_email
 * @property string $entry_id
 * @property Collection $entry
 *
 */
class EntryVersion extends Model
{
    protected string $collection = 'entry_version';
    protected string $permissionGroup = 'entryversion'; // Usage only in get methods
    protected array $casting = [
        "entry" => Collection::class
    ];

    public const DATABASE_ERROR = '5200: Exception when "%s" an entry version.';
    public const CANNOT_APPLY_LAST_VERSION = '5201: Cannot apply last version, it is the same as the current version.';

    /**
     *
     * Get an entry version by id
     *
     * @param string|ObjectId $entryVersionId
     * @return EntryVersion|null
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     */
    public function getById(string|ObjectId $entryVersionId): ?EntryVersion
    {
        $this->hasPermissions(true);

        return $this->findById($entryVersionId)->exec();
    }

    /**
     *
     * Get entry versions for a given entry id
     *
     * @param string|ObjectId $entryId
     * @return array|Model|EntryVersion|null
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function getVersionsByEntryId(string|ObjectId $entryId): array|Model|EntryVersion|null
    {
        $this->hasPermissions(true);

        return $this->find(['entry_id' => (string)$entryId])->exec();
    }

    /**
     *
     * Create a new version for an entry, automatically called in the create and update of an entry.
     *
     * @param User $user
     * @param array $simplifyEntry
     * @return string
     * @throws EntryException
     *
     */
    public function create(User $user, array $simplifyEntry): string
    {
        try {
            $entryVersionId = $this->insert([
                'created_at' => time(),
                'user_id' => (string)$user->_id,
                'user_full_name' => $user->name->full,
                'user_email' => $user->email,
                'entry_id' => $simplifyEntry['_id'],
                'entry' => $simplifyEntry,
            ]);
        } catch (DatabaseException $exception) {
            throw new EntryException(sprintf(self::DATABASE_ERROR, 'creating') . PHP_EOL . $exception->getMessage());
        }

        return (string)$entryVersionId;
    }

    /**
     *
     * Delete all version by entry id
     *
     * @param string $entry_id
     * @return bool
     * @throws EntryException
     *
     */
    public function deleteAllByEntryId(string $entry_id): bool
    {
        try {
            $result = $this->deleteMany(['entry_id' => $entry_id]);
        } catch (DatabaseException $exception) {
            throw new EntryException(sprintf(self::DATABASE_ERROR, 'deleting') . PHP_EOL . $exception->getMessage());
        }

        return $result > 0;
    }

    /**
     *
     * Apply a version to an entry
     *  TODO maybe manage this call to not be able to apply an already applied version. It's possible to apply a same version multiple times.
     *
     * @param string $entry_version_id
     * @return bool
     * @throws DatabaseException
     * @throws EntryException
     * @throws JsonException
     * @throws FilesystemException
     * @throws ACLException
     * @throws PermissionException
     * @throws SodiumException
     *
     */
    public function applyVersion(string $entry_version_id): bool
    {
        $this->hasPermissions();

        $entryVersion = $this->findById($entry_version_id)->exec();

        if (!isset($entryVersion->_id)) {
            return false;
        }

        // Check if not the last version
        $lastVersion = $this->findOne([
            "entry_id" => $entryVersion->entry_id
        ], QueryOptions::initWithSort(['_id' => -1]))->exec();

        if ((string)$lastVersion->_id === (string)$entryVersion->_id) {
            throw new EntryException(self::CANNOT_APPLY_LAST_VERSION);
        }

        $data = [
            'parent' => $entryVersion->entry->get('parent'),
            'site_id' => $entryVersion->entry->get('site_id'),
            'locale' => $entryVersion->entry->get('locale'),
            'status' => $entryVersion->entry->get('status'),
            'title' => $entryVersion->entry->get('title'),
            'slug' => $entryVersion->entry->get('slug'),
            'template' => $entryVersion->entry->get('template'),
            'categories' => $entryVersion->entry->get('categories'),
            'content' => $entryVersion->entry->get('content'),
            'alternates' => $entryVersion->entry->get('alternates'),
        ];

        $entryType = (new EntryType())->findById($entryVersion->entry->get('entry_type_id'))->exec();
        $entryModel = $entryType->getEntryModel($entryType);

        try {
            $result = $entryModel->updateById($entryVersion->entry->get('_id'), $data, true, true);
        } catch (DatabaseException $exception) {
            throw new EntryException(sprintf(self::DATABASE_ERROR, 'applying') . PHP_EOL . $exception->getMessage());
        }

        return $result->length === 0;
    }
}