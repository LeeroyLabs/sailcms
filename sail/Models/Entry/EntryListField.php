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

class EntryListField extends Field
{
    public const MULTIPLE = true;

    /**
     *
     * Override the constructor to force the repeater attribute to true
     *
     * @param  LocaleField            $labels
     * @param  array|Collection|null  $settings
     */
    public function __construct(LocaleField $labels, array|Collection|null $settings = null)
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
            'en' => 'Allows the selection of an list of entries from a list.',
            'fr' => 'Permet la sélection d\'une liste d\'entrées à partir d\'une liste.'
        ]);
    }

    /**
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

        if ($content instanceof Collection || is_array($content)) {
            $entryPublicationModel = new EntryPublication();
            $entryPublications = $entryPublicationModel->getPublicationsByEntryIds($content, false, false);

            $missingEntries = new Collection();
            foreach ($content as $i => $entryId) {
                $missing = true;
                foreach ($entryPublications as $publication) {
                    if ($publication->entry_id === $entryId) {
                        $missing = false;
                        break;
                    }
                }
                if ($missing) {
                    $missingEntries->pushKeyValue($i, EntryField::ENTRY_DOES_NOT_EXISTS);
                }
            }

            if ($missingEntries->length > 0) {
                $errors->push($missingEntries);
            }
        }

        return $errors;
    }

    /**
     *
     * Parent override to get the list of entries
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
        if ($content instanceof Collection || is_array($content)) {
            $entryPublicationModel = new EntryPublication();
            $entryPublications = $entryPublicationModel->getPublicationsByEntryIds($content, true, false);

            $publicationEntries = new Collection();
            foreach ($entryPublications as $publication) {
                /**
                 * @var EntryPublication $publication
                 */
                $publicationEntries->push($publication->version->entry->unwrap());
            }
            return $publicationEntries;
        }

        return $content;
    }
}