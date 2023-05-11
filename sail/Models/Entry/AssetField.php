<?php

namespace SailCMS\Models\Entry;

use Exception;
use SailCMS\Collection;
use SailCMS\Models\Asset;
use SailCMS\Types\Fields\InputTextField;
use SailCMS\Types\LocaleField;
use SailCMS\Types\StoringType;

class AssetField extends Field
{
    public bool $isImage;

    const ASSET_DOES_NOT_EXISTS = '6280: Asset of the given id does not exists.';

    public function __construct(LocaleField $labels, array|Collection|null $settings = null, bool $isImage = true)
    {
        $settings['isImage'] = $isImage;
        $this->isImage = $isImage;

        parent::__construct($labels, $settings);
    }

    /**
     *
     * Description for field info
     *
     * @return string
     *
     */
    public function description(): string
    {
        return 'Field to implement an asset input.';
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
        $defaultSettings = new Collection(['required' => false, 'isImage' => true]);
        return new Collection([
            $defaultSettings
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
            InputTextField::class
        ]);
    }

    /**
     *
     * Validate the asset existence
     *
     * @param mixed $content
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
     * @param mixed $content
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