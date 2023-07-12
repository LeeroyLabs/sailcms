<?php

namespace SailCMS\Models\Entry;

use Exception;
use SailCMS\Collection;
use SailCMS\Models\Asset;
use SailCMS\Types\FieldCategory;
use SailCMS\Types\Fields\InputAssetFileField;
use SailCMS\Types\LocaleField;
use SailCMS\Types\StoringType;
use stdClass;

class AssetFileField extends Field
{
    public const REPEATABLE = true;


    public const ASSET_DOES_NOT_EXISTS = '6280: Asset of the given id does not exists.';

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
            InputAssetFileField::defaultSettings()
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

        if ($this->repeater) {
            $repeaterErrors = Collection::init();
            $content->each(function ($key, $assetId) use ($repeaterErrors) {
                if (!$this->validateAsset($assetId)) {
                    $repeaterErrors->pushKeyValue($key, self::ASSET_DOES_NOT_EXISTS);
                }
            });

            if ($repeaterErrors->length > 0) {
                $errors->push($repeaterErrors);
            }

        } else if (!$this->validateAsset($content)) {
            $errors->push(new Collection([self::ASSET_DOES_NOT_EXISTS]));
        }

        return $errors;
    }

    /**
     *
     * Validate if the asset exists
     *
     * @param $assetId
     * @return bool
     *
     */
    private function validateAsset($assetId): bool
    {
        try {
            $asset = Asset::getById($assetId);
        } catch (Exception $exception) {
            // fail silently
            $asset = null;
        }

        return boolval($asset);
    }

    /**
     *
     * Parse the content to return the asset url and name
     *
     * @param  mixed  $content
     * @return stdClass|array
     *
     */
    public function parse(mixed $content): stdClass|array
    {
        if ($this->repeater) {
            $assets = [];
            $content->each(function ($key, $assetId) use (&$assets) {
                $assets[] = $this->parseAsset($assetId);
            });
            return $assets;
        }
        return $this->parseAsset($content);

    }

    /**
     *
     * Parse asset
     *
     * @param $assetId
     * @return stdClass
     *
     */
    private function parseAsset($assetId): stdClass
    {
        $assetData = [];

        if ($assetId) {
            try {
                $asset = Asset::getById($assetId);
            } catch (Exception $exception) {
                // fail silently
                $asset = null;
            }

            if ($asset) {
                $assetData = [
                    'name' => $asset->name,
                    'url' => $asset->url
                ];
            }
        }

        return (object)$assetData;
    }
}