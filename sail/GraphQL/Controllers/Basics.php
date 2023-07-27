<?php

namespace SailCMS\GraphQL\Controllers;

use JsonException;
use League\Flysystem\FilesystemException;
use SailCMS\Collection;
use SailCMS\Errors\DatabaseException;
use SailCMS\GlobalSeo;
use SailCMS\GraphQL\Context;
use SailCMS\Sail;
use SailCMS\Types\SeoDefaultConfig;
use SailCMS\Types\SeoSettings;
use SodiumException;

class Basics
{
    public function version(): string
    {
        return Sail::SAIL_VERSION;
    }

    /**
     * @throws DatabaseException
     * @throws FilesystemException
     * @throws SodiumException
     * @throws JsonException
     */
    public function getSeoDefaultConfig(mixed $obj, Collection $args, Context $context): ?SeoDefaultConfig
    {
        return (new SeoDefaultConfig((array)GlobalSeo::getSeoDefaultConfig()->config));
    }

    /**
     * @throws DatabaseException
     * @throws FilesystemException
     * @throws SodiumException
     * @throws JsonException
     */
    public function getSeoSettings(mixed $obj, Collection $args, Context $context): ?SeoSettings
    {
        return (new SeoSettings((array)GlobalSeo::getSeoSettings()->config));
    }

    /**
     * @throws DatabaseException
     * @throws FilesystemException
     * @throws SodiumException
     * @throws JsonException
     */
    public function setSeoDefaultConfig(mixed $obj, Collection $args, Context $context): ?bool
    {
        (new GlobalSeo())->setSeoDefaultConfig((new SeoDefaultConfig([
            'title' => $args->get('config')['title'],
            'description' => $args->get('config')['description'],
            'keywords' => $args->get('config')['keywords'],
            'robots' => $args->get('config')['robots'],
            'sitemap' => $args->get('config')['sitemap'],
            'default_image' => $args->get('config')['default_image'],
            'social_metas' => $args->get('config')['social_metas']
        ])));
        return true;
    }

    /**
     * @throws DatabaseException
     * @throws FilesystemException
     * @throws SodiumException
     * @throws JsonException
     */
    public function setSeoSettings(mixed $obj, Collection $args, Context $context): ?bool
    {
        (new GlobalSeo())->setSeoSettings((new SeoSettings([
            'separator_character' => $args->get('settings')['separator_character'],
            'sitename' => $args->get('settings')['sitename'],
            'sitename_position' => $args->get('settings')['sitename_position']
        ])));
        return true;
    }
}