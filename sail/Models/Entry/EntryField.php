<?php

namespace SailCMS\Models\Entry;

use SailCMS\Collection;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\EntryException;
use SailCMS\Errors\PermissionException;
use SailCMS\Models\EntryType;
use SailCMS\Types\Fields\InputTextField;
use SailCMS\Types\StoringType;

class EntryField extends Field
{
    /* Error */
    const ENTRY_TYPE_DOES_NOT_EXISTS = '6160: Entry of %s type does not exists.';
    const ENTRY_DOES_NOT_EXISTS = '6161: Entry of the given id does not exists.';
    const ENTRY_ID_AND_HANDLE = '6162: Entry id and entry type handle must be set both or none';

    public function description(): string
    {
        return "Field to add a link to an entry";
    }

    public function storingType(): string
    {
        return StoringType::ARRAY->value;
    }

    public function defaultSettings(): Collection
    {
        // The only settings available is "required"
        $defaultSettings = new Collection(['required' => true]);
        return new Collection([
            'id' => $defaultSettings,
            'typeHandle' => $defaultSettings
        ]);
    }

    protected function defineBaseConfigs(): void
    {
        $this->baseConfigs = new Collection([
            'id' => InputTextField::class,
            'typeHandle' => InputTextField::class
        ]);
    }

    /**
     *
     * Entry validation
     *
     * @param Collection $content
     * @return Collection|null
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    protected function validate(Collection $content): ?Collection
    {
        $errors = Collection::init();

        $entryId = $content->get('id');
        $entryTypeHandle = $content->get('typeHandle');

        if ((!$entryId && $entryTypeHandle) || (!$entryTypeHandle && $entryId)) {
            // This a general field error so it's directly at the root of the errors array
            $errors->push(self::ENTRY_ID_AND_HANDLE);
        } else if ($entryId && $entryTypeHandle) {
            $entryModel = EntryType::getEntryModelByHandle($entryTypeHandle);

            if (!$entryModel->entry_type_id) {
                $typeHandleError = new Collection(['typeHandle' => sprintf(self::ENTRY_TYPE_DOES_NOT_EXISTS, $entryTypeHandle)]);
                $errors->push($typeHandleError);
            }

            if (!$entryModel->one(['_id' => $entryId])) {
                $idError = new Collection(['id' => self::ENTRY_DOES_NOT_EXISTS]);
                $errors->push($idError);
            }
        }

        return $errors;
    }

    /**
     *
     * Parent override to get the entry
     *
     * @param $content
     * @return mixed
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function parse($content): mixed
    {
        if ($content instanceof \stdClass) {
            $entryId = $content->id;
            $entryTypeHandle = $content->typeHandle;

            try {
                $entryModel = EntryType::getEntryModelByHandle($entryTypeHandle);
                $entry = $entryModel->getById($entryId);
            } catch (EntryException $exception) {
                // Fail silently
                return new \stdClass();
            }
            // Get publication instead ?
            return $entry->simplify(null);
        }

        return $content;
    }
}