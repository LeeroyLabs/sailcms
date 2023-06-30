<?php

namespace SailCMS;

use SailCMS\Types\UI\NavigationElement;
use SailCMS\Types\UI\SettingsElement;

class UI
{
    private static Collection $navigationElements;
    private static Collection $settingsElements;

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
     * Add Settings Page Element to the UI
     *
     * @param  SettingsElement  $element
     * @return void
     *
     */
    public static function addSettingsElement(SettingsElement $element): void
    {
        if (!isset(self::$settingsElements)) {
            self::$settingsElements = new Collection(['entries' => [], 'emails' => [], 'others' => []]);
        }

        self::$settingsElements[$element->section]->push($element);
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

    /**
     *
     * Get settings elements to display on the UI
     *
     * @return Collection
     *
     */
    public static function getSettingsElements(): Collection
    {
        if (!isset(self::$settingsElements)) {
            self::$settingsElements = new Collection(['entries' => [], 'emails' => [], 'others' => []]);
        }

        return self::$settingsElements;
    }
}