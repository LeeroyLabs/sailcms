<?php

namespace SailCMS;

/**
 * Field utilities
 *
 * LIST of potential FIELDS
 *
 * TextField // option uppercase
 * NumberField
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
 * MatrixField
 *
 */
class Field
{
    // static get available fields
    public static function getAvailableFields(): Collection
    {
        $fieldList = Collection::init();
        $fields = new Collection(glob(__DIR__ . '/Models/Entry/*.php'));
        // TODO put fields in a collection
        return Collection::init();
    }
}