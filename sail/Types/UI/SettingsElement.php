<?php

namespace SailCMS\Types\UI;

use SailCMS\Text;
use SailCMS\Types\LocaleField;

class SettingsElement
{
    const SECTION_ENTRIES = 'entries';
    const SECTION_EMAILS = 'emails';
    const SECTION_OTHERS = 'others';

    public readonly string $name;
    public readonly string $icon;
    public readonly string $url;
    public readonly LocaleField $label;
    public readonly string $permission;
    public readonly string $section;
    public readonly string $slug;
    public readonly string $parent;

    public function __construct(
        string $name,
        string $icon,
        string $url,
        LocaleField $label,
        string $permission = 'any',
        string $section = self::SECTION_OTHERS
    ) {
        $this->name = $name;
        $this->icon = 'mdi-' . $icon;
        $this->label = $label;
        $this->slug = Text::from($label->get('en'))->slug()->value();
        $this->permission = $permission;
        $this->section = $section;
        $this->parent = 'Settings';

        if (str_starts_with($url, '/')) {
            $this->url = substr($url, 1);
        } else {
            $this->url = $url;
        }
    }
}