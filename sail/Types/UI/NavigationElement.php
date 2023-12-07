<?php

namespace SailCMS\Types\UI;

use SailCMS\Text;
use SailCMS\Types\LocaleField;

class NavigationElement
{
    const SECTION_POST_ENTRIES = 'post_entries';
    const SECTION_PRE_USERS = 'pre_users';
    const SECTION_PRE_SETTINGS = 'pre_settings';

    public readonly string $name;
    public readonly string $icon;
    public readonly string $url;
    public readonly LocaleField $label;
    public readonly string $parent;
    public readonly string $permission;
    public readonly string $section;
    public readonly string $slug;

    public function __construct(
        string $name,
        string $icon,
        string $url,
        LocaleField $label,
        string $parent = '',
        string $permission = 'any',
        string $section = self::SECTION_PRE_SETTINGS
    ) {
        $this->name = $name;
        $this->icon = $icon;
        $this->label = $label;
        $this->slug = Text::from($label->get('en'))->slug()->value();
        $this->parent = $parent;
        $this->permission = $permission;
        $this->section = $section;

        if (str_starts_with($url, '/')) {
            $this->url = substr($url, 1);
        } else {
            $this->url = $url;
        }
    }
}