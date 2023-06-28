<?php

namespace SailCMS\Models\Entry;

use SailCMS\Collection;
use SailCMS\Errors\DatabaseException;
use SailCMS\Models\Category;
use SailCMS\Types\FieldCategory;
use SailCMS\Types\Fields\InputTextField;
use SailCMS\Types\LocaleField;
use SailCMS\Types\StoringType;

class CategoryField extends Field
{
    /* Errors 6300 to 6319 */
    public const CATEGORY_DOES_NOT_EXIST = '6300: Category of the given id does not exists';

    public function description(): LocaleField
    {
        return new LocaleField([
            'en' => 'Allows the selection of a category from a list.',
            'fr' => 'Permet la sélection d\'une catégorie à partir d\'une liste.'
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
     * Storing type
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
        $defaultSettings = new Collection(['required' => false]);
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
     * Verify that the category exist
     *
     * @param  mixed  $content
     * @return Collection|null
     * @throws DatabaseException
     */
    protected function validate(mixed $content): ?Collection
    {
        $errors = Collection::init();

        if (is_string($content)) {
            if (!Category::getById($content)) {
                $errors->push(new Collection([self::CATEGORY_DOES_NOT_EXIST]));
            }
        }

        return $errors;
    }

    /**
     *
     * Transform id to the category object
     *
     * @param  mixed  $content
     * @return mixed
     * @throws DatabaseException
     *
     */
    public function parse(mixed $content): mixed
    {
        if (is_string($content)) {
            return Category::getById($content);
        }
        return $content;
    }
}