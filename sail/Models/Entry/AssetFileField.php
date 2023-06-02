<?php

namespace SailCMS\Models\Entry;

use Exception;
use SailCMS\Collection;
use SailCMS\Models\Asset;
use SailCMS\Types\FieldCategory;
use SailCMS\Types\Fields\InputAssetFileField;
use SailCMS\Types\Fields\InputAssetImageField;
use SailCMS\Types\Fields\InputHTMLField;
use SailCMS\Types\Fields\InputTextField;
use SailCMS\Types\LocaleField;
use SailCMS\Types\StoringType;

class AssetFileField extends Field
{
    public const ASSET_DOES_NOT_EXISTS = '6280: Asset of the given id does not exists.';

    public function __construct(LocaleField $labels, array|Collection|null $settings = null)
    {
        parent::__construct($labels, $settings);
    }

    /**
     *
     * Description for field info
     *
     * @return LocaleField
     *
     */
    public function description(): LocaleField
    {
        return new LocaleField([
            'en' => 'Field that allows selection one or more files.',
            'fr' => 'Champ qui permet la sélection d\'une ou plusieurs fichiers.'
        ]);
    }

    /**
     *
     * Category of field
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
     * Returns the storing type
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
     * Sets the default settings from the input text field
     *
     * @return Collection
     *
     */
    public function defaultSettings(): Collection
    {
        return new Collection([
            new Collection([
                'required' => false,
                'multiple' => false,
                'allowedFormats' => '.pdf,.doc,.docx,.xls,.xlsx'
            ])
        ]);
    }

    /**
     *
     * The text field contain only an input text field
     *
     * @return void
     */
    protected function defineBaseConfigs(): void
    {
        $this->baseConfigs = new Collection([
            InputAssetFileField::class
        ]);
    }

    /**
     *
     * Validate the asset existence
     *
     * @param  mixed  $content
     * @return Collection|null
     *
     */
    protected function validate(mixed $content): ?Collection
    {
        $errors = Collection::init();

        try {
            $asset = Asset::getById($content);
        } catch (Exception $exception) {
            // fail silently
            $asset = null;
        }

        if (!$asset) {
            $errors->push(new Collection([self::ASSET_DOES_NOT_EXISTS]));
        }

        return $errors;
    }

    /**
     *
     * Parse the content to return the asset url and name
     *
     * @param  mixed  $content
     * @return mixed
     *
     */
    public function parse(mixed $content): mixed
    {
        if ($content) {
            try {
                $asset = Asset::getById($content);
            } catch (Exception $exception) {
                // fail silently
                $asset = null;
            }

            if ($asset) {
                return (object)[
                    'name' => $asset->name,
                    'url' => $asset->url
                ];
            }
        }
        return $content;
    }
}