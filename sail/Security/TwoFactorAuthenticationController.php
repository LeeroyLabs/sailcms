<?php

namespace SailCMS\Security;

use JsonException;
use League\Flysystem\FilesystemException;
use RobThree\Auth\TwoFactorAuthException;
use Sail\Encryption;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\FileException;
use SailCMS\Http\Response;
use SailCMS\Models\Tfa;
use SailCMS\Security;
use SodiumException;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class TwoFactorAuthenticationController
{
    private Response $response;

    /**
     *
     * @throws TwoFactorAuthException
     * @throws FilesystemException | DatabaseException|SodiumException
     *
     */
    public function __construct(public string $uid)
    {
        $this->response = new Response();
        $this->response->template = 'v1_tfa';

        $tfa = new TwoFactorAuthentication();
        $model = new Tfa();
        $secret = $model->getForUser($this->uid);

        if ($secret === null) {
            $code = $tfa->signup();
            $model->setForUser($this->uid, $code);
        } else {
            $code = $secret->secret;
        }

        $url = parse_url($_SERVER['HTTP_HOST']);

        $this->response->set('overrideColor', setting('tfa.main_color', ''));
        $this->response->set('overrideHoverColor', setting('tfa.hover_color', ''));
        $this->response->set('uid', $uid);
        $this->response->set('qr', $tfa->getQRCode($code));
        $this->response->set('host', $url['host']);
    }

    /**
     *
     * Handle code validation
     *
     * @param  string  $code
     * @return void
     * @throws DatabaseException
     * @throws FilesystemException
     * @throws SodiumException
     *
     */
    public function validate(string $code): void
    {
        $this->response = new Response();
        $this->response->setType('json');

        $tfa = new TwoFactorAuthentication();

        $model = new Tfa();
        $secret = $model->getForUser($this->uid);

        $this->response->set('uid', $this->uid);

        if ($secret === null) {
            $this->response->set('valid', false);
            return;
        }

        $this->response->set('codes', $secret->codes);
        $this->response->set('valid', $tfa->validate($secret->secret, $code));
    }

    /**
     *
     * Render template
     *
     * @return void
     * @throws JsonException
     * @throws FilesystemException
     * @throws FileException
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     *
     */
    public function render(): void
    {
        $this->response->render(false);
    }
}