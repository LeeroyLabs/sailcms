<?php

namespace SailCMS\Models;

use MongoDB\BSON\ObjectId;
use SailCMS\Collection;
use SailCMS\Database\Model;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\EntryException;

/**
 *
 * @property int $created_at
 * @property string $user_id
 * @property string $user_full_name
 * @property string $user_email
 * @property string $entry_id // for delete query
 * @property Collection $entry // result of Entry castFrom, and it'll be used in the applyVersion method
 *
 */
class EntryVersion extends Model
{
    protected string $collection = 'entry_version';
    protected string $permissionGroup = 'entryversion'; // Usage only in get methods

    public const DATABASE_ERROR = '5100: Exception when "%s" an entry seo.';

    public function getVersionByEntryId(string|ObjectId $entryId)
    {
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
                'create_at' => time(),
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

    public function applyVersion(string $entry_version_id)
    {
        $entryVersion = $this->findById($entry_version_id);

        if (!isset($entryVersion->_id)) {
            return false;
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

        $entryType = (new EntryType())->findById($entryVersion->entry->get('entry_type_id'));
        $entryModel = $entryType->getEntryModel($entryType);

        $entryModel->updateById($entryVersion->entry->get('_id'), $data);
    }
}