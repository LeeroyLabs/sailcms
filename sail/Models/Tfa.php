<?php

namespace SailCMS\Models;

use League\Flysystem\FilesystemException;
use SailCMS\Collection;
use SailCMS\Database\Model;
use SailCMS\Errors\DatabaseException;
use SailCMS\Security;
use SodiumException;
use Ramsey\Uuid\Uuid;

class Tfa extends Model
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
     * @param  string  $userId
     * @return ?Tfa
     * @throws DatabaseException
     * @throws FilesystemException
     * @throws SodiumException
     *
     */
    public function getForUser(string $userId): ?Tfa
    {
        $entry = $this->findOne(['user_id' => $userId])->exec();

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
     * @param  string  $userId
     * @param  string  $secret
     * @throws DatabaseException
     * @throws FilesystemException
     *
     */
    public function setForUser(string $userId, string $secret): void
    {
        $enc = Security::encrypt($secret);

        // Generate rescue codes
        $codes = [
            Security::encrypt(Uuid::uuid7()),
            Security::encrypt(Uuid::uuid7()),
            Security::encrypt(Uuid::uuid7()),
            Security::encrypt(Uuid::uuid7())
        ];

        $this->deleteOne(['user_id' => $userId]);
        $this->insert(['user_id' => $userId, 'secret' => $enc, 'codes' => $codes]);
    }

    /**
     *
     * Rescue account with 2FA rescue codes
     *
     * @param  Collection|array  $codes
     * @return User|null
     * @throws DatabaseException
     *
     */
    public function rescueAccount(Collection|array $codes): ?User
    {
        if (!is_array($codes)) {
            $codes = $codes->unwrap();
        }

        $record = $this->findOne(['codes' => $codes[0]])->exec();

        if ($record) {
            $code1 = $record->codes->at(0);
            $code2 = $record->codes->at(1);
            $code3 = $record->codes->at(2);
            $code4 = $record->codes->at(3);

            if ($code1 === $codes[0] && $code2 === $codes[1] && $code3 === $codes[2] && $code4 === $codes[3]) {
                return User::loginFromRescue($record->user_id);
            }
        }

        return null;
    }
}