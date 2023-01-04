<?php

namespace SailCMS;

use Exception;
use League\Flysystem\FilesystemException;
use SailCMS\Errors\EmailException;
use SailCMS\Errors\FileException;
use SailCMS\Models\Email;
use Symfony\Bridge\Twig\Mime\BodyRenderer;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Address;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class Mail
{
    private TemplatedEmail $email;

    public const PRIORITY_LOWEST = 5;
    public const PRIORTIY_LOW = 4;
    public const PRIORITY_NORMAL = 3;
    public const PRIORITY_HIGH = 2;
    public const PRIORITY_HIGHEST = 1;

    public function __construct()
    {
        $this->email = new TemplatedEmail();
    }

    /**
     *
     * Set from
     *
     * @param  string  $email
     * @return $this
     *
     */
    public function from(string $email): static
    {
        $this->email->from($email);
        return $this;
    }

    /**
     *
     * Set from with a full name eg: John Doe <john@doe.com>
     *
     * @param  string  $name
     * @param  string  $email
     * @return $this
     *
     */
    public function fromWithName(string $name, string $email): static
    {
        $this->email->from(Address::create("{$name} <{$email}>"));
        return $this;
    }

    /**
     *
     * Set Targets
     *
     * @param  string  ...$emails
     * @return $this
     *
     */
    public function to(string ...$emails): static
    {
        $this->email->to(...$emails);
        return $this;
    }

    /**
     *
     * Set CC
     *
     * @param  string  ...$emails
     * @return $this
     *
     */
    public function cc(string ...$emails): static
    {
        $this->email->cc(...$emails);
        return $this;
    }

    /**
     *
     * Set BCC
     *
     * @param  string  ...$emails
     * @return $this
     *
     */
    public function bcc(string ...$emails): static
    {
        $this->email->bcc(...$emails);
        return $this;
    }

    /**
     *
     * Set subject line
     *
     * @param  string  $subject
     * @return $this
     *
     */
    public function subject(string $subject): static
    {
        $this->email->subject($subject);
        return $this;
    }

    /**
     *
     * Attach a file from the local disk to the email
     *
     * @param  string  $path
     * @param  string  $name
     * @return $this
     * @throws FilesystemException
     *
     */
    public function attachment(string $path, string $name): static
    {
        $fs = Filesystem::manager();
        $this->email->attach($fs->readStream($path), $name);
        return $this;
    }

    /**
     *
     * Embed an image to the email, in your email you must use the CID format
     * eg: for image named 'my-image' you must us <img src="cid:my-image"/>
     *
     * @param  string  $path
     * @param  string  $name
     * @return $this
     * @throws FilesystemException
     *
     */
    public function embed(string $path, string $name): static
    {
        $fs = Filesystem::manager();
        $this->email->embed($fs->readStream($path), Text::kebabCase(Text::deburr($name)));
        return $this;
    }

    /**
     *
     * Set replyTo
     *
     * @param  string  $email
     * @return $this
     *
     */
    public function replyTo(string $email): static
    {
        $this->email->replyTo($email);
        return $this;
    }

    /**
     *
     * Set priority
     *
     * @param  int  $priority
     * @return $this
     *
     */
    public function priority(int $priority): static
    {
        $this->email->priority($priority);
        return $this;
    }

    /**
     *
     * Set the twig template to use for the email
     *
     * @param  string      $name
     * @param  Collection  $context
     * @return $this
     *
     */
    public function template(string $name, Collection $context): static
    {
        $path = $name . '.twig';
        $this->email->htmlTemplate($path);
        $this->email->context($context->unwrap());
        return $this;
    }

    /**
     *
     * Set the text version of the email
     *
     * @param  string  $text
     * @return $this
     *
     */
    public function text(string $text): static
    {
        $this->email->text($text);
        return $this;
    }

    /**
     *
     * Send the email
     *
     * @return bool
     * @throws EmailException
     *
     */
    public function send(): bool
    {
        $mailer = new Mailer(Transport::fromDsn(env('mail_dsn', '')));
        $loader = new FilesystemLoader(Sail::getTemplateDirectory());
        $twigEnv = new Environment($loader);

        try {
            $twigBodyRenderer = new BodyRenderer($twigEnv);
            $twigBodyRenderer->render($this->email);
        } catch (Exception $e) {
            throw new EmailException($e, 0500);
        }

        try {
            $mailer->send($this->email);
            return true;
        } catch (TransportExceptionInterface $e) {
            return false;
        }
    }

    /**
     *
     * Setup email to use a template from the templating system
     *
     * @param  string            $slug
     * @param  string            $locale
     * @param  Collection|array  $context
     * @return $this
     * @throws Errors\DatabaseException
     * @throws FileException
     * @throws EmailException
     *
     */
    public function useEmail(string $slug, string $locale, Collection|array $context = []): static
    {
        $template = Email::getBySlug($slug);

        if ($template) {
            $settings = setting('emails', []);

            if (is_array($context)) {
                $context = new Collection($context);
            }

            $providedContent = new Collection([
                'email_title' => $template->title->{$locale},
                'email_content' => $template->content->{$locale},
                'cta_link' => $template->cta->{$locale},
                'cta_title' => $template->cta_title->{$locale}
            ]);

            // Context squashes providedContent to enable extensibility
            $superContext = $providedContent->merge($context);

            // Fetch the global scoped context
            $globalContext = setting('emails.globalContext', ['locales' => ['fr' => [], 'en' => []]]);
            $locales = $globalContext->get('locales.' . $locale);
            $gc = $globalContext->unwrap();
            unset($gc['locales']); // don't add twice

            $superContext->pushSpreadKeyValue(...$locales->unwrap(), ...$gc);

            // cta_link is present and verification_code. Replace {code} in link for code
            if ($context->get('verification_code', null) !== null) {
                $superContext->setFor('cta_link', str_replace(
                    '{code}',
                    $context->get('verification_code', ''),
                    $superContext->get('cta_link')
                ));
            }

            // cta_link is present and code. Replace {code} in link for code
            if ($context->get('reset_code', null) !== null) {
                $superContext->setfor('cta_link', str_replace(
                    '{code}',
                    $context->get('reset_code', ''),
                    $superContext->get('cta_link')
                ));
            }

            // Replace locale variable in template name to the actual locale
            $template->template = str_replace('{locale}', $locale, $template->template);

            return $this
                ->from($settings->get('from'))
                ->subject($template->subject->{$locale})
                ->template($template->template, $superContext);
        }

        throw new EmailException("Cannot find the email from database, please make sure it's not a typo", 0404);
    }
}