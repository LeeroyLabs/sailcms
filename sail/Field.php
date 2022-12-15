<?php

namespace SailCMS;

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
    public static function getAvailableFields(): Collection
    {
        $fieldList = Collection::init();
        $fields = new Collection(glob(__DIR__ . '/Models/Entry/*.php'));
        // TODO put fields in a collection to be able to send it to graphql + create a info method in entry/field
        return Collection::init();
    }
}