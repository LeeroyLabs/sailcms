<?php

namespace SailCMS;

use SailCMS\Contracts\SeoAdapter;
use SailCMS\Seo\Entry;
use SailCMS\Seo\EntryType;
use SailCMS\Seo\System;

class Seo
{
    /**
     *
     * // Getters
     * Seo::entry('page')->get('xxxxxxxxxx');
     * Seo::entryType('page')->get();
     * Seo::global()->get();
     *
     * // Setters
     * Seo::entry('page')->set('xxxxxxxxxx', {});
     * Seo::entryType('page')->set({});
     * Seo::global()->set({});
     *
     * // Manipulation
     * Seo::entry('page')->get('xxxxx')->robots->tag(); (output the robots meta tag)
     * Seo::global()->sitemap(); (output the sitemap xml)
     *
     *
     * // Redirections
     *
     *
     */

    public static function entry(string $type = 'page'): SeoAdapter
    {
        return new Entry($type);
    }

    public static function entryType(string $type = 'page'): SeoAdapter
    {
        return new EntryType($type);
    }

    public static function global(): SeoAdapter
    {
        return new System();
    }

    public static function regenerateSiteMap()
    {
        $system = new System();
        $system->regenerateSiteMap();
    }
}