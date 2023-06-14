<?php

namespace SailCMS;

use SailCMS\Types\UI\NavigationElement;

class UI
{
    private static Collection $navigationElements;

    /**
     *
     * Add Navigation elements to the UI
     *
     * @param  NavigationElement  $element
     * @return void
     *
     */
    public static function addNavigationElement(NavigationElement $element): void
    {
        if (!isset(self::$navigationElements)) {
            self::$navigationElements = new Collection(['post_entries' => [], 'pre_users' => [], 'pre_settings' => []]);
        }

        self::$navigationElements[$element->section]->push($element);
    }

    /**
     *
     * Get navigation elements to display on the UI
     *
     * @return Collection
     *
     */
    public static function getNavigationElements(): Collection
    {
        if (!isset(self::$navigationElements)) {
            self::$navigationElements = new Collection(['post_entries' => [], 'pre_users' => [], 'pre_settings' => []]);
        }

        return self::$navigationElements;
    }
}