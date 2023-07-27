<?php

namespace SailCMS;

use JsonException;
use League\Flysystem\FilesystemException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Models\Config;
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

    public function generateSitemap():void
    {

    }
}