<?php

namespace SailCMS;

use JsonException;
use League\Flysystem\FilesystemException;
use SailCMS\Database\Model;
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
     *
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
     *
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
     *
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
     *
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
     *
     */
    public function generateRobot(string $template, array $parameters = []):void
    {
        try {
            $content = (new Engine())->render($template, new Collection([$parameters]));
            file_put_contents('../robots.txt', $content);
        } catch (LoaderError|RuntimeError|SyntaxError $e) {
        }
    }

    /**
     *
     * Return robots.txt
     *
     * @return string
     *
     */
    public static function getRobot():string
    {
        return file_get_contents('../robots.txt');
    }

    /**
     *
     * Generate sitemaps from twig template
     *
     * @param string $template
     * @param string $filename
     * @param array $parameters
     * @return void
     *
     */
    public function generateSitemap(string $template, string $filename, array $parameters = []):void
    {
        try {
            $content = (new Engine())->render($template, new Collection([$parameters]));
            file_put_contents('../'. $filename .'.xml', $content);
        } catch (LoaderError|RuntimeError|SyntaxError $e) {
        }
    }

    /**
     *
     * Return sitemap.xml
     *
     * @return array
     *
     */
    public static function getSitemap():array
    {
        $sitemaps_filename = glob('../sitemap*.xml');
        $sitemaps = [];

        foreach ($sitemaps_filename as $key => $filename) {
            $sitemaps[$key]['filename'] = $filename;
            $sitemaps[$key]['content'] = file_get_contents($filename);
        }

        return $sitemaps;
    }

    /**
     *
     * Return Google Tag Manager script
     *
     * @return string
     *
     */
    public static function gtag(): string
    {
        $tag_id = self::getSeoSettings()->config->gtag;
        ray($tag_id);

        return "<!-- Google tag (gtag.js) -->
            <script async src=\"https://www.googletagmanager.com/gtag/js?id=$tag_id\"></script>
            <script>
                window.dataLayer = window.dataLayer || [];
                function gtag(){dataLayer.push(arguments);}
                gtag('js', new Date());
                gtag('config', '$tag_id');
            </script>";
    }
}