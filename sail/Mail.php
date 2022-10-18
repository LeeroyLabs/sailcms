<?php

namespace SailCMS;

use League\Flysystem\FilesystemException;
use SailCMS\Errors\FileException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Address;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

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
     * @throws FileException
     *
     */
    public function template(string $name, Collection $context): static
    {
        $path = Sail::getTemplateDirectory() . '/' . $name . '.twig';

        if (!file_exists($path)) {
            throw new FileException("Could not find template {$path} for email template. Please make sure it's not a mistake.", 0404);
        }

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
     *
     */
    public function send(): bool
    {
        $mailer = new Mailer(Transport::fromDsn($_ENV['MAIL_DSN']));

        try {
            $mailer->send($this->email);
            return true;
        } catch (TransportExceptionInterface $e) {
            return false;
        }
    }
}