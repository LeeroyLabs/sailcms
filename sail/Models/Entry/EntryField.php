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
        // Force to be required in the default settings
        $requiredTextDefault = InputTextField::defaultSettings();
        // $requiredTextDefault['required'] = true;

        return new Collection([
            'entryId' => $requiredTextDefault,
            'entryTypeHandle' => $requiredTextDefault
        ]);
    }

    protected function defineBaseConfigs(): void
    {
        $this->baseConfigs = new Collection([
            'entryId' => InputTextField::class,
            'entryTypeHandle' => InputTextField::class
        ]);
    }

    /**
     *
     *
     *
     * @param  Collection  $content
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

        $entryId = $content->get('entryId');
        $entryTypeHandle = $content->get('entryTypeHandle');

        $entryModel = EntryType::getEntryModelByHandle($entryTypeHandle);

        if (!$entryModel->entry_type_id) {
            $errors->push(sprintf(self::ENTRY_TYPE_DOES_NOT_EXISTS, $entryTypeHandle));
        }

        if ($entryModel->getCount(['_id' => $entryId]) !== 1) {
            $errors->push(self::ENTRY_DOES_NOT_EXISTS);
        }

        return $errors;
    }
}