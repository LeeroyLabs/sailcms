<?php

namespace SailCMS\Types\Fields;

use SailCMS\Collection;
use SailCMS\Types\LocaleField;
use SailCMS\Types\StoringType;
use stdClass;

class InputEmailField extends Field
{
    /* Errors 6240 to 6259 */
    public const EMAIL_INVALID = '6240: This email is invalid';

    /**
     *
     * Input text field from html input:text attributes
     *
     * @param LocaleField|null $labels
     * @param bool $required
     */
    public function __construct(
        public readonly ?LocaleField $labels = null,
        public readonly bool         $required = false,
    )
    {
    }

    /**
     *
     * Define default settings for a Input Text Field
     *
     * @return Collection
     *
     */
    public static function defaultSettings(): Collection
    {
        return new Collection([
            'required' => false,
        ]);
    }

    /**
     *
     * Available properties of the settings
     *
     * @param array|null $options
     * @return Collection
     */
    public static function availableProperties(?array $options = null): Collection
    {
        return new Collection([
            new InputSettings('required', InputSettings::INPUT_TYPE_CHECKBOX),
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
     * @param mixed $content
     * @return Collection
     *
     */
    public function validate(mixed $content): Collection
    {
        $errors = Collection::init();

        if ($this->required && !$content) {
            $errors->push(self::FIELD_REQUIRED);
        }

        if ($content) {
            if (!filter_var($content, FILTER_VALIDATE_EMAIL)) {
                $errors->push(self::EMAIL_INVALID);
            }
        }

        return $errors;
    }

    /**
     *
     * Cast to simpler form from InputTextField
     *
     * @return stdClass
     *
     */
    public function castFrom(): stdClass
    {
        return (object)[
            'labels' => $this->labels->castFrom(),
            'settings' => [
                'required' => $this->required
            ]
        ];
    }

    /**
     *
     * Cast to InputTextField
     *
     * @param mixed $value
     * @return InputEmailField
     *
     */
    public function castTo(mixed $value): self
    {
        return new self(
            $value->labels,
            $value->settings->required ?? false,
        );
    }
}