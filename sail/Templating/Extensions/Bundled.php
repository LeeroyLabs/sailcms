<?php

namespace SailCMS\Templating\Extensions;

use Exception;
use ImagickException;
use League\Flysystem\FilesystemException;
use SailCMS\Assets\Transformer;
use SailCMS\Debug;
use SailCMS\Errors\DatabaseException;
use SailCMS\Locale;
use SailCMS\Models\Asset;
use SailCMS\Sail;
use SailCMS\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class Bundled extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('version', [$this, 'version']),
            new TwigFunction('header', [$this, 'header']),
            new TwigFunction('debug', [$this, 'debug']),
            new TwigFunction('env', [$this, 'env']),
            new TwigFunction('publicPath', [$this, 'publicPath']),
            new TwigFunction('locale', [$this, 'getLocale']),
            new TwigFunction('__', [$this, 'translate']),
            new TwigFunction('twoFactor', [$this, 'twoFactor']),
            new TwigFunction('csrf', [$this, 'csrf']),
            new TwigFunction('transform', [$this, 'transform'])
        ];
    }

    public function getFilters(): array
    {
        return [];
    }

    public function header(): void
    {
        // TODO: Implement for SEO
    }

    /**
     *
     * Get current sail version
     *
     * @return string
     *
     */
    public function version(): string
    {
        return Sail::SAIL_VERSION;
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
        Debug::dump($data);
    }

    /**
     *
     * Get an .env variable
     *
     * @param  string  $key
     * @param  mixed   $default
     * @return string|array|bool
     *
     */
    public function env(string $key, mixed $default): mixed
    {
        return env($key);
    }

    /**
     *
     * get a setting
     *
     * @param  string  $key
     * @param  mixed   $default
     * @return mixed
     *
     */
    public function setting(string $key, mixed $default): mixed
    {
        return setting($key);
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
        return '/public';
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

    /**
     *
     * Generate CSRF token
     *
     * @return string
     * @throws Exception
     *
     */
    public function csrf(): string
    {
        $use = setting('CSRF.use', true);

        if ($use) {
            $token = Security::csrf();
            return '<input type="hidden" name="_csrf_" value="' . $token . '" />';
        }

        return '';
    }

    /**
     *
     * Transform an asset
     *
     * @param  string    $id
     * @param  string    $name
     * @param  int|null  $width
     * @param  int|null  $height
     * @param  string    $crop
     * @return string
     * @throws ImagickException
     * @throws FilesystemException
     * @throws DatabaseException
     *
     */
    public function transform(string $id, string $name, ?int $width = null, ?int $height = null, string $crop = Transformer::CROP_CC): string
    {
        return Asset::transformById($id, $name, $width, $height);
    }

    /**
     *
     * Obfuscate an email address
     *
     * @param  string  $email
     * @return string
     * @throws Exception
     *
     */
    public function obfuscateEmail(string $email): string
    {
        $alwaysEncode = ['.', ':', '@'];
        $result = '';

        // Encode string using oct and hex character codes
        $length = strlen($email);
        for ($i = 0; $i < $length; $i++) {
            // Encode 25% of characters including several that always should be encoded
            if (in_array($email[$i], $alwaysEncode) || random_int(1, 100) < 25) {
                if (random_int(0, 1)) {
                    $result .= '&#' . ord($email[$i]) . ';';
                } else {
                    $result .= '&#x' . dechex(ord($email[$i])) . ';';
                }
            } else {
                $result .= $email[$i];
            }
        }

        return $result;
    }

    /**
     *
     * Create an obfuscated mailto link with optional label
     *
     * @param  string  $email
     * @param  string  $label
     * @return string
     * @throws Exception
     *
     */
    public function obfuscateMailto(string $email, string $label = ''): string
    {
        $email = $this->obfuscateEmail($email);

        if ($label === '') {
            $label = $email;
        }

        return '<a href="mailt&#111;&#58;' . $email . '" rel="nofollow">' . $label . '</a>';
    }
}