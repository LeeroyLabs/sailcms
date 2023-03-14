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
    // TODO : change input field for a select field that choices are entry and entry type
    // TODO : add parse method to include in Entry content Getter ??
    const SEARCHABLE = false;

    /* Error */
    const ENTRY_TYPE_DOES_NOT_EXISTS = '6160: Entry of %s type does not exists.';
    const ENTRY_DOES_NOT_EXISTS = '6161: Entry of the given id does not exists.';
    const ENTRY_ID_AND_HANDLE = '6161: Entry id and entry type handle must be set both or none';

    public function description(): string
    {
        return "Field to add a link to an entry";
    }

    public function storingType(): string
    {
        return StoringType::ARRAY->name;
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
            $errors->push(self::ENTRY_ID_AND_HANDLE);
        } else if ($entryId && $entryTypeHandle) {
            $entryModel = EntryType::getEntryModelByHandle($entryTypeHandle);

            if (!$entryModel->entry_type_id) {
                $errors->push(sprintf(self::ENTRY_TYPE_DOES_NOT_EXISTS, $entryTypeHandle));
            }

            if (!$entryModel->one(['_id' => $entryId])) {
                $errors->push(self::ENTRY_DOES_NOT_EXISTS);
            }
        }

        return $errors;
    }
}