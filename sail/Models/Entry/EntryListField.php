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

class EntryListField extends Field
{
    /* Error */
    const ENTRY_TYPE_DOES_NOT_EXISTS = '6260: Entry of %s type does not exists.';
    const ENTRY_HANDLE = '6261: Entry type handle must be set';

    public function description(): string
    {
        return "Field to add a list of links of entries";
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
            'typeHandle' => $defaultSettings
        ]);
    }

    protected function defineBaseConfigs(): void
    {
        $this->baseConfigs = new Collection([
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

        $entryTypeHandle = $content->get('typeHandle');

        if (!$entryTypeHandle) {
            $errors->push(self::ENTRY_HANDLE);
        } else {
            $entryModel = EntryType::getEntryModelByHandle($entryTypeHandle);

            if (!$entryModel->entry_type_id) {
                $errors->push(sprintf(self::ENTRY_TYPE_DOES_NOT_EXISTS, $entryTypeHandle));
            }
        }

        return $errors;
    }
}