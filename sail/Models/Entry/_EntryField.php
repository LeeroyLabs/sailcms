<?php
//
//namespace SailCMS\Models\Entry;
//
//use SailCMS\Collection;
//use SailCMS\Errors\ACLException;
//use SailCMS\Errors\DatabaseException;
//use SailCMS\Errors\EntryException;
//use SailCMS\Errors\FieldException;
//use SailCMS\Errors\PermissionException;
//use SailCMS\Models\EntryType;
//
//// TODO make the entry field
//class EntryField extends Field
//{
//    /* Error */
//    const ENTRY_DOES_NOT_EXISTS = 'Entry of %s type does not exists.';
//
//    protected function defineSchema(): void
//    {
//        $this->configs = new Collection([
//            'entry_id' => self::TYPE_STRING,
//            'entry_type_handle' => self::TYPE_STRING
//        ]);
//    }
//
//    protected function required(): array
//    {
//        return ['entry_id', 'entry_type_handle'];
//    }
//
//    /**
//     *
//     *
//     *
//     * @param Collection $content
//     * @return Collection|null
//     * @throws ACLException
//     * @throws DatabaseException
//     * @throws EntryException
//     * @throws FieldException
//     * @throws PermissionException
//     *
//     */
//    protected function validate(Collection $content): ?Collection
//    {
//        $entryId = $this->content->get('entry_id');
//        $entryTypeHandle = $this->content->get('entry_type_handle');
//
//        $entryModel = EntryType::getEntryModelByHandle($entryTypeHandle);
//        if ($entryModel->getCount(['_id' => $entryId]) != 1) {
//            throw new FieldException(sprintf(self::ENTRY_DOES_NOT_EXISTS, $entryTypeHandle));
//        }
//    }
//
//    public function defaultSettings(): Collection
//    {
//        // TODO: Implement defaultSettings() method.
//    }
//
//    protected function defineBaseConfigs(): void
//    {
//        // TODO: Implement defineBaseConfigs() method.
//    }
//}