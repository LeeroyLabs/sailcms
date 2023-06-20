<?php

namespace SailCMS\Models\Entry;

use SailCMS\Collection;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\PermissionException;
use SailCMS\Models\EntryPublication;
use SailCMS\Types\FieldCategory;
use SailCMS\Types\Fields\InputTextField;
use SailCMS\Types\LocaleField;
use SailCMS\Types\StoringType;

class EntryField extends Field
{
    /* Error */
    public const ENTRY_DOES_NOT_EXISTS = '6160: Entry of the given id does not exists or is not published.';

    /**
     *
     * Override the constructor to force the repeater attribute to false
     *
     * @param  LocaleField            $labels
     * @param  array|Collection|null  $settings
     */
    public function __construct(LocaleField $labels, array|Collection|null $settings)
    {
        parent::__construct($labels, $settings);
    }

    /**
     *
     * @return LocaleField
     *
     */
    public function description(): LocaleField
    {
        return new LocaleField([
            'en' => 'Allows the selection of an entry from a list.',
            'fr' => 'Permet la sélection d\'une entrée à partir d\'une liste.'
        ]);
    }

    /**
     *
     *
     * @return string
     *
     */
    public function category(): string
    {
        return FieldCategory::SPECIAL->value;
    }

    /**
     *
     * @return string
     *
     */
    public function storingType(): string
    {
        return StoringType::STRING->value;
    }

    /**
     *
     * @return Collection
     *
     */
    public function defaultSettings(): Collection
    {
        // The only settings available is "required"
        $defaultSettings = new Collection(['required' => true]);
        return new Collection([
            $defaultSettings
        ]);
    }

    /**
     *
     * @return void
     *
     */
    protected function defineBaseConfigs(): void
    {
        $this->baseConfigs = new Collection([
            InputTextField::class
        ]);
    }

    /**
     *
     * Entry validation
     *
     * @param  mixed  $content
     * @return Collection|null
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    protected function validate(mixed $content): ?Collection
    {
        $errors = Collection::init();

        if (is_string($content)) {
            $entryPublicationModel = new EntryPublication();
            if (!$entryPublicationModel->getPublicationByEntryId($content)) {
                $errors->push(new Collection([self::ENTRY_DOES_NOT_EXISTS]));
            }
        }

        return $errors;
    }

    /**
     *
     * Parent override to get the entry data
     *
     * @param mixed $content
     * @return mixed
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function parse(mixed $content): mixed
    {
        if (is_string($content)) {
            $entryPublicationModel = new EntryPublication();
            $entryPublication = $entryPublicationModel->getPublicationByEntryId($content, true, false);

            return $entryPublication->version->entry->unwrap();
        }

        return $content;
    }
}