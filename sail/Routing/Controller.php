<?php

namespace SailCMS\Routing;

use SailCMS\Attributes\Routing\Route;
use SailCMS\Contracts\AppController;
use SailCMS\Sail;
use SailCMS\Templating\Engine;
use SailCMS\Types\Http;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class Controller extends AppController
{
    /**
     *
     * Load third-party UI content
     *
     * @param  string  $name
     * @param  string  $path
     * @return void
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     *
     */
    #[Route('/extension/:string/:all', Http::GET, 'en', 'load_3rd_party_app')]
    public function loadThirdPartyApplication(string $name, string $path): void
    {
        if ($path === '') {
            $path = 'ui';
        }

        $uiPath = Sail::getWorkingDirectory() . '/containers/' . ucfirst($name) . '/ui';

        switch ($path) {
            case 'ui':
                if (file_exists($uiPath . '/index.twig')) {
                    // Parse Twig file
                    $engine = new Engine();
                    Engine::addTemplatePath(dirname($uiPath, 2));
                    $context = (object)['handshakeKey' => env('EXTENSION_HANDSHAKE_KEY', '')];

                    echo $engine->render(ucfirst($name) . '/ui/index', $context);
                } else {
                    // Load HTML file
                    echo file_get_contents(Sail::getWorkingDirectory() . '/containers/' . ucfirst($name) . '/ui/index.html');
                }
                break;

            case 'settings':
                if (file_exists($uiPath . '/settings.twig')) {
                    // Parse Twig file
                    $engine = new Engine();
                    Engine::addTemplatePath(dirname($uiPath, 2));
                    $context = (object)['handshakeKey' => env('EXTENSION_HANDSHAKE_KEY', '')];

                    echo $engine->render(ucfirst($name) . '/ui/settings', $context);
                } else {
                    // Load HTML file
                    echo file_get_contents(Sail::getWorkingDirectory() . '/containers/' . ucfirst($name) . '/ui/settings.html');
                }
                break;

            default:
                $extension = substr($path, strrpos($path, '.') + 1);

                switch ($extension) {
                    case 'js':
                        header('Content-Type: text/javascript');
                        break;

                    case 'json':
                        header('Content-Type: application/json');
                        break;

                    case 'jpg':
                    case 'jpeg':
                        header('Content-Type: image/jpeg');
                        break;

                    case 'png':
                        header('Content-Type: image/png');
                        break;

                    case 'webp':
                        header('Content-Type: image/webp');
                        break;

                    case 'svg':
                        header('Content-Type: image/svg+xml');
                        break;

                    case 'css':
                        header('Content-Type: text/css');
                        break;

                    case 'ttf':
                        header('Content-Type: application/x-font-truetype');
                        break;

                    case 'otf':
                        header('Content-Type: application/x-font-opentype');
                        break;

                    case 'woff':
                        header('Content-Type: application/font-woff');
                        break;

                    case 'woff2':
                        header('Content-Type: application/font-woff2');
                        break;

                    case 'eot':
                        header('Content-Type: application/vnd.ms-fontobject');
                        break;

                    case 'sfnt':
                        header('Content-Type: application/font-sfnt');
                        break;
                }

                echo file_get_contents(Sail::getWorkingDirectory() . '/containers/' . ucfirst($name) . '/ui/' . $path);
                break;
        }

        die();
    }
}