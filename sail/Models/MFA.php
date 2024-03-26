<?php

namespace SailCMS\Models;

use League\Flysystem\FilesystemException;
use SailCMS\Collection;
use SailCMS\Database\Model;
use SailCMS\Errors\DatabaseException;
use SailCMS\Security;
use SodiumException;
use Ramsey\Uuid\Uuid;

/**
 *
 * @property string     $user_id
 * @property string     $secret
 * @property Collection $codes
 *
 */
class MFA extends Model
{
    protected string $collection = 'mfa_data';

    /**
     *
     * Get the Secret for given user
     *
     * @param  string  $userId
     * @return ?MFA
     * @throws DatabaseException
     * @throws FilesystemException
     * @throws SodiumException
     *
     */
    public function getForUser(string $userId): ?MFA
    {
        $entry = $this->findOne(['user_id' => $userId])->exec();

        if (!empty($entry)) {
            $entry->secret = Security::decrypt($entry->secret);
            $entry->codes = new Collection([
                Security::decrypt($entry->codes->at()),
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

        User::setMFA($userId);
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
            $code1 = $record->codes->at();
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