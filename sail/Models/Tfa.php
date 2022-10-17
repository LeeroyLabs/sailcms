<?php

namespace SailCMS\Models;

use League\Flysystem\FilesystemException;
use SailCMS\Collection;
use SailCMS\Database\BaseModel;
use SailCMS\Errors\DatabaseException;
use SailCMS\Security;
use SodiumException;
use Ramsey\Uuid\Uuid;

class Tfa extends BaseModel
{
    public string $user_id = '';
    public string $secret = '';
    public Collection $codes;

    public function __construct()
    {
        parent::__construct('tfa_data');
    }

    public function fields(bool $fetchAllFields = false): array
    {
        return ['_id', 'user_id', 'secret', 'codes'];
    }

    /**
     *
     * Get the Secret for given user
     *
     * @param  string  $user_id
     * @return ?Tfa
     * @throws DatabaseException
     * @throws FilesystemException
     * @throws SodiumException
     *
     */
    public function getForUser(string $user_id): ?Tfa
    {
        $entry = $this->findOne(['user_id' => $user_id])->exec();

        if (!empty($entry)) {
            $entry->secret = Security::decrypt($entry->secret);
            $entry->codes = new Collection([
                Security::decrypt($entry->codes->at(0)),
                Security::decrypt($entry->codes->at(1)),
                Security::decrypt($entry->codes->at(2)),
                Security::decrypt($entry->codes->at(3))
            ]);
            return $entry;
        }

        return null;
    }

    /**
     *
     * Set the hash version secret for the user
     *
     * @param  string  $user_id
     * @param  string  $secret
     * @throws DatabaseException
     * @throws FilesystemException
     *
     */
    public function setForUser(string $user_id, string $secret): void
    {
        $enc = Security::encrypt($secret);

        // Generate rescue codes
        $codes = [
            Security::encrypt(Uuid::uuid7()),
            Security::encrypt(Uuid::uuid7()),
            Security::encrypt(Uuid::uuid7()),
            Security::encrypt(Uuid::uuid7())
        ];

        $this->deleteOne(['user_id' => $user_id]);
        $this->insert(['user_id' => $user_id, 'secret' => $enc, 'codes' => $codes]);
    }
}