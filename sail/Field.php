<?php

namespace SailCMS;

use SailCMS\Models\Entry\Field as ModelField;

/**
 * Field utilities
 *
 * LIST of potential FIELDS
 *
 * abstract Field                                   => Errors 6100 to 6119
 * TextField // option uppercase                    => Errors 6120 to 6139
 * NumberField // option float + negative number    => Errors 6140 to 6159
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
    private static Collection $registered;

    public static function init(): void
    {
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

        self::$registered = $fieldList;
    }

    public static function loadCustom(Collection $customFields): void
    {
        self::$registered->pushSpread(...$customFields->unwrap());
    }

    /**
     *
     * Get available fields
     *
     * @return Collection
     *
     */
    public static function getAvailableFields(): Collection
    {
        if (!isset(self::$registered)) {
            self::init();
        }
        return self::$registered;
    }
}