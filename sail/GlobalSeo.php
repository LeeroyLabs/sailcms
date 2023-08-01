<?php

namespace SailCMS;

use JsonException;
use League\Flysystem\FilesystemException;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\EntryException;
use SailCMS\Errors\PermissionException;
use SailCMS\Models\Config;
use SailCMS\Models\Entry;
use SailCMS\Models\EntrySeo;
use SailCMS\Models\EntryType;
use SailCMS\Templating\Engine;
use SailCMS\Types\SeoDefaultConfig;
use SailCMS\Types\SeoSettings;
use SodiumException;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

final class GlobalSeo
{
    /**
     *
     * Set default seo config
     *
     * @param SeoDefaultConfig $config
     * @throws DatabaseException
     * @throws FilesystemException
     * @throws JsonException
     * @throws SodiumException
     * @return void
     */
    public function setSeoDefaultConfig(SeoDefaultConfig $config):void
    {
        Config::setByName('default_seo', $config);
    }

    /**
     *
     * Get default seo config
     *
     * @throws DatabaseException
     * @throws FilesystemException
     * @throws JsonException
     * @throws SodiumException
     * @return Config
     */
    public static function getSeoDefaultConfig():Config
    {
        return Config::getByName('default_seo');
    }

    /**
     *
     * Set seo settings
     *
     * @param SeoSettings $settings
     * @throws DatabaseException
     * @throws FilesystemException
     * @throws JsonException
     * @throws SodiumException
     * @return void
     */
    public function setSeoSettings(SeoSettings $settings):void
    {
        Config::setByName('seo_settings', $settings);
    }

    /**
     *
     * Get seo settings
     *
     * @throws DatabaseException
     * @throws FilesystemException
     * @throws JsonException
     * @throws SodiumException
     * @return Config
     */
    public static function getSeoSettings():Config
    {
        return Config::getByName('seo_settings');
    }

    /**
     *
     * Generate robot.txt from twig template
     *
     * @param string $template
     * @param array $parameters
     * @return void
     */
    public function generateRobot(string $template, array $parameters = []):void
    {
        try {
            $content = (new Engine())->render($template, new Collection([$parameters]));
            file_put_contents('robots.txt', $content);
        } catch (LoaderError|RuntimeError|SyntaxError $e) {
        }
    }

    /**
     *
     * Generate all sitemaps
     *
     * @throws DatabaseException
     * @throws ACLException
     * @throws PermissionException
     * @throws EntryException
     */
    public function generateSitemap():void
    {
        $entryTypes = EntryType::getAll();

        $file = '<?xml version="1.0" encoding="UTF-8"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">';
        $entryTypes->each(static function($key, $value) use (&$file) {
            $entries = Entry::getList($value->handle);

            $sitemapName = "sitemap_sections_" . $value->handle . ".xml";
            $file .= '<sitemap><loc>' . env('SITE_URL') . '/' . $sitemapName . '</loc><lastmod>' . date("Y-m-d") . "T" . date("H:m:sO") . '</lastmod></sitemap>';

            $sitemap = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
            foreach ($entries->list as $entry) {
                $entrySeo = (new EntrySeo())->getByEntryId($entry->_id)->sitemap;
                if ($entrySeo) {
                    $url = env('SITE_URL') . '/' . $entry->locale . '/' . $entry->slug;
                    $sitemap .= '<url><loc>' . $url . '</loc></url>';
                }
            }

            $sitemap .= '</urlset>';
            file_put_contents($sitemapName, $sitemap);
        });
        $file .= '</sitemapindex>';
        file_put_contents('sitemap.xml', $file);
    }
}