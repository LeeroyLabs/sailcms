<?php

namespace SailCMS\Types\Fields;

use SailCMS\Collection;
use SailCMS\Types\LocaleField;
use SailCMS\Types\StoringType;
use stdClass;

class InputAssetImageField extends Field
{
    /**
     *
     * Input text field from html input:text attributes
     *
     * @param  LocaleField|null  $labels
     * @param  bool              $required
     * @param  bool              $multiple
     * @param  string|null       $cropName
     * @param  int|null          $ratio
     * @param  int|null          $minWidth
     * @param  int|null          $minHeight
     * @param  int|null          $maxWidth
     * @param  int|null          $maxHeight
     * @param  string|null       $lockedType
     *
     */
    public function __construct(
        public readonly ?LocaleField $labels = null,
        public readonly bool $required = false,
        public readonly bool $multiple = false,
        public readonly ?string $cropName = null,
        public readonly ?int $ratio = 0,
        public readonly ?int $minWidth = 200,
        public readonly ?int $minHeight = 200,
        public readonly ?int $maxWidth = 2000,
        public readonly ?int $maxHeight = 2000,
        public readonly ?string $lockedType = ''
    ) {
    }

    /**
     *
     * Define default settings for a Input Text Field
     *
     * @param  bool  $multiline
     * @return Collection
     *
     */
    public static function defaultSettings(bool $multiline = false): Collection
    {
        return new Collection([
            'required' => false,
            'multiple' => false,
            'cropName' => 'custom',
            'ratio' => 0,
            'minWidth' => 200,
            'minHeight' => 200,
            'maxWidth' => 2000,
            'maxHeight' => 2000,
            'lockedType' => ''
        ]);
    }

    /**
     *
     * Available properties of the settings
     *
     * @return Collection
     *
     */
    public static function availableProperties(): Collection
    {
        $options = new Collection([
            ['value' => '', 'label' => 'Unlocked'],
            ['value' => 'square', 'label' => 'Square'],
            ['value' => 'round', 'label' => 'Round']
        ]);

        return new Collection([
            new InputSettings('required', InputSettings::INPUT_TYPE_CHECKBOX),
            new InputSettings('multiple', InputSettings::INPUT_TYPE_CHECKBOX),
            new InputSettings('cropName', InputSettings::INPUT_TYPE_STRING, Collection::init(), true),
            new InputSettings('ratio', InputSettings::INPUT_TYPE_NUMBER),
            new InputSettings('minWidth', InputSettings::INPUT_TYPE_NUMBER),
            new InputSettings('minHeight', InputSettings::INPUT_TYPE_NUMBER),
            new InputSettings('maxWidth', InputSettings::INPUT_TYPE_NUMBER),
            new InputSettings('maxHeight', InputSettings::INPUT_TYPE_NUMBER),
            new InputSettings('lockedType', InputSettings::INPUT_TYPE_OPTIONS, $options, true)
        ]);
    }

    /**
     *
     * Get the type of how it's store in the database
     *
     * @return string
     */
    public static function storingType(): string
    {
        return StoringType::STRING->value;
    }

    /**
     *
     * Input text field validation
     *
     * @param  mixed  $content
     * @return Collection
     *
     */
    public function validate(mixed $content): Collection
    {
        $errors = Collection::init();

        if ($this->required && !$content) {
            $errors->push(self::FIELD_REQUIRED);
        }

        return $errors;
    }

    /**
     *
     * Cast to InputTextField
     *
     * @param  mixed  $value
     * @return InputAssetImageField
     *
     */
    public function castTo(mixed $value): self
    {
        return new self(
            $value->labels,
            $value->settings->required ?? false,
            $value->settings->multiple ?? false,
            $value->settings->cropName ?? 'custom',
            $value->settings->ratio ?? 0,
            $value->settings->minWidth ?? 200,
            $value->settings->minHeight ?? 200,
            $value->settings->maxWidth ?? 2000,
            $value->settings->maxHeight ?? 2000,
            $value->settings->lockedType ?? ''
        );
    }
}