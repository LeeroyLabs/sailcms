<?php

namespace SailCMS\Models\Entry;

use SailCMS\Collection;
use SailCMS\Errors\DatabaseException;
use SailCMS\Models\Category;
use SailCMS\Types\FieldCategory;
use SailCMS\Types\Fields\InputTextField;
use SailCMS\Types\LocaleField;
use SailCMS\Types\StoringType;

class CategoryListField extends Field
{
    /**
     *
     * Override the constructor to force the repeater property to be set to true
     *
     * @param  LocaleField            $labels
     * @param  array|Collection|null  $settings
     *
     */
    public function __construct(LocaleField $labels, array|Collection|null $settings = null)
    {
        parent::__construct($labels, $settings, true);
    }

    /**
     *
     * @return LocaleField
     *
     */
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
     * Verify that the categories555 exist
     *
     * @param  mixed  $content
     * @return Collection|null
     * @throws DatabaseException
     */
    protected function validate(mixed $content): ?Collection
    {
        $errors = Collection::init();

        if ($content instanceof Collection) {
            $content = $content->unwrap();
        }

        if (is_array($content)) {
            $categories = Category::getByIds($content);

            $missingCategories = Collection::init();
            foreach ($content as $i => $categoryId) {
                $missing = true;
                foreach ($categories as $category) {
                    if ((string)$category->id === $categoryId) {
                        $missing = false;
                        break;
                    }
                }

                if ($missing) {
                    $missingCategories->pushKeyValue($i, CategoryField::CATEGORY_DOES_NOT_EXIST);
                }
            }

            if ($missingCategories->length > 0) {
                $errors->push($missingCategories);
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
        if ($content instanceof Collection) {
            $content = $content->unwrap();
        }

        if (is_array($content)) {
            return Category::getByIds($content);
        }
        return $content;
    }
}