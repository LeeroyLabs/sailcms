<?php

namespace SailCMS;

use SailCMS\Models\Entry\Field as ModelField;

/**
 * Field utilities
 *
 * LIST of potential FIELDS
 *
 * TextField // option uppercase
 * NumberField // option float
 * DateField
 * DateTimeField
 * EmailField
 * HtmlField
 * UrlField
 *
 * ChoiceField // option radio / check
 * SelectField // search...
 *
 * AssetField
 * EntryField
 * EntryListField
 * CategoryField // to talk about
 *
 * ObjectField
 *
 */
class Field
{
    // static get available fields
    public static function getAvailableFields(string $locale): Collection
    {
        Locale::setCurrent($locale);

        $fieldList = Collection::init();
        $fields = new Collection(glob(__DIR__ . '/Models/Entry/*.php'));

        $fields->each(function ($key, $file) use ($fieldList) {
            $name = substr(basename($file), 0, -4);

            if ($name != "Field" && !str_starts_with($name, "_")) {
                /**
                 * @var ModelField $class
                 */
                $class = 'SailCMS\\Models\\Entry\\' . $name;
                $fieldList->push($class::info());
            }
        });

        return $fieldList;
    }
}