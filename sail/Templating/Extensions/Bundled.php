<?php

namespace SailCMS\Templating\Extensions;

use SailCMS\Locale;
use SailCMS\Sail;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class Bundled extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('debug', [$this, 'debug']),
            new TwigFunction('env', [$this, 'env']),
            new TwigFunction('publicPath', [$this, 'publicPath']),
            new TwigFunction('locale', [$this, 'getLocale']),
            new TwigFunction('__', [$this, 'translate']),
            new TwigFunction('twoFactor', [$this, 'twoFactor'])
        ];
    }

    public function getFilters(): array
    {
        return [];
    }

    /**
     *
     * Debug your variable within Twig
     *
     * @param  mixed  $data
     * @return void
     *
     */
    public function debug(mixed $data): void
    {
        echo '<pre>';
        print_r($data);
        echo '</pre>';
    }

    /**
     *
     * Get an .env variable
     *
     * @param  string  $key
     * @return string|array|bool
     *
     */
    public function env(string $key): string|array|bool
    {
        return getenv($key);
    }

    /**
     *
     * Get the public directory for assets
     *
     * @return string
     *
     */
    public function publicPath(): string
    {
        return '/public/' . Sail::currentApp();
    }

    /**
     *
     * Get current Locale
     *
     * @return string
     *
     */
    public function getLocale(): string
    {
        return Locale::$current;
    }

    /**
     *
     * Translate a string
     *
     * @param  string  $path
     * @return string
     *
     */
    public function translate(string $path): string
    {
        return Locale::translate($path);
    }

    /**
     *
     * Return the iframe code for the 2FA Set up UI
     *
     * @param  string  $userId
     * @return string
     *
     */
    public function twoFactor(string $userId): string
    {
        $locale = Locale::$current;

        if ($locale !== 'fr' && $locale !== 'en') {
            $locale = 'en';
        }

        $url = '/v1/tfa/' . $locale . '/' . $userId;

        return <<<HTML
            <iframe 
                id="twofactorui" 
                src="{$url}" 
                onload="(function(o){o.style.height=o.contentWindow.document.body.scrollHeight+'px';}(this));" 
                style="border: 0; min-width: 325px;">
            </iframe>

            <script>
                let tfaHeight = 0;
                
                window.addEventListener('resize', (e) =>
                {
                    let frame = document.getElementById('twofactorui');
                    frame.style.height = frame.contentWindow.document.body.scrollHeight + 'px';
                    tfaHeight = frame.contentWindow.document.body.scrollHeight;
                });
                
                window.addEventListener('DOMContentLoaded', (e) =>
                {
                    let frame = document.getElementById('twofactorui');
                    frame.contentWindow.document.body.addEventListener('resize', () => {
                        if (tfaHeight !== frame.contentWindow.document.body.scrollHeight) {
                            tfaHeight = frame.contentWindow.document.body.scrollHeight;                            
                            frame.style.height = frame.contentWindow.document.body.scrollHeight + 'px';
                        }
                    });
                });
            </script>
            HTML;
    }
}