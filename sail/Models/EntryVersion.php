<?php

namespace SailCMS\Models;

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


    public function deleteAllByEntryId(string $entry_id): bool
    {
        try {
            $result = $this->deleteMany(['entry_id' => $entry_id]);
        } catch (DatabaseException $exception) {
            throw new EntryException(sprintf(self::DATABASE_ERROR, 'deleting') . PHP_EOL . $exception->getMessage());
        }

        return $result > 0;
    }
}