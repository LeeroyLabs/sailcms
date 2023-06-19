<?php

namespace SailCMS;

use Exception;
use League\Flysystem\FilesystemException;
use SailCMS\Errors\EmailException;
use SailCMS\Errors\FileException;
use SailCMS\Http\Request;
use SailCMS\Models\Email;
use SailCMS\Templating\Engine;
use SailCMS\Templating\Extensions\Bundled;
use SailCMS\Templating\Extensions\Navigation;
use Symfony\Bridge\Twig\Mime\BodyRenderer;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Yaml\Yaml;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Loader\FilesystemLoader;

class Mail
{
    private TemplatedEmail $email;

    private static array $registeredPreviewers = [];

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
     * Register a preview handler
     *
     * @param  string  $template
     * @param  string  $class
     * @param  string  $method
     * @return void
     * @throws EmailException
     *
     */
    public static function registerPreviewHandler(string $template, string $class, string $method): void
    {
        if (!isset(self::$registeredPreviewers[$template])) {
            $instance = new $class();
            self::$registeredPreviewers[$template] = ['template' => $template, 'class' => $instance, 'method' => $method];
            return;
        }

        throw new EmailException('Cannot register more than 1 preview handler for ' . $template, 0403);
    }

    /**
     *
     * Get list of preview handlers for given template
     *
     * @param  string  $template
     * @return array|null
     *
     */
    public static function getRegisteredPreviewHandler(string $template): array|null
    {
        return self::$registeredPreviewers[$template] ?? null;
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
        $this->email->embed($fs->readStream($path), Text::from($name)->deburr()->kebab()->value());
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
     * @throws LoaderError
     *
     */
    public function send(): bool
    {
        $mailer = new Mailer(Transport::fromDsn(env('mail_dsn', '')));
        $loader = new FilesystemLoader(Sail::getTemplateDirectory());
        $loader->addPath(dirname(__DIR__) . '/install');

        $twigEnv = new Environment($loader);

        // Load all Twig extensions, functions and filters
        $extensions = Engine::getExtensions();

        $twigEnv->addExtension(new Bundled());
        $twigEnv->addExtension(new Navigation());

        foreach ($extensions['extensions'] as $ext) {
            $twigEnv->addExtension($ext);
        }

        foreach ($extensions['filters'] as $filter) {
            $twigEnv->addFilter($filter);
        }

        foreach ($extensions['functions'] as $func) {
            $twigEnv->addFunction($func);
        }

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
     * Get the Emails configuration and parse it
     *
     * @param  string  $siteId
     * @return Collection
     *
     */
    public static function loadAndParseTemplates(string $siteId): Collection
    {
        $file = Sail::getWorkingDirectory() . '/config/emails.' . $siteId . '.yaml';
        $yaml = Yaml::parse(file_get_contents($file));

        $list = [];

        foreach ($yaml as $file => $config) {
            $list[] = (object)[
                'name' => $file,
                'configs' => $config
            ];
        }

        return new Collection($list);
    }

    /**
     *
     * Version selector for this feature
     *
     * @param  int               $version
     * @param  string            $slug
     * @param  string            $locale
     * @param  Collection|array  $context
     * @return $this
     * @throws EmailException
     * @throws Errors\DatabaseException
     * @throws FileException
     *
     */
    public function useEmail(int $version, string $slug, string $locale, Collection|array $context = []): static
    {
        if ($version === 1) {
            return $this->useEmailVersion1($slug, $locale, $context);
        }

        return $this->useEmailVersion2($slug, $locale, $context);
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
    public function useEmailVersion2(string $slug, string $locale, Collection|array $context = []): static
    {
        if ($slug === 'test') {
            $settings = setting('emails', []);

            if (is_array($context)) {
                $context = new Collection($context);
            }

            return $this
                ->from($settings->get('from'))
                ->subject('SailCMS Test')
                ->template('test', $context);
        }

        $template = Email::getBySlug($slug);

        if ($template) {
            $settings = setting('emails', []);

            if (is_array($context)) {
                $context = new Collection($context);
            }

            $fields = [
                'subject' => $template->subject->{$locale}
            ];

            foreach ($template->fields as $field) {
                $fields[$field->key] = $field->value->{$locale};
            }

            $providedContent = new Collection($fields);

            // Context squashes providedContent to enable extensibility
            $superContext = $providedContent->merge($context);

            // Fetch the global scoped context
            $globalContext = setting('emails.globalContext', ['locales' => ['fr' => [], 'en' => []]]);
            $locales = $globalContext->get('locales.' . $locale);
            $gc = $globalContext->unwrap();
            unset($gc['locales']); // don't add twice

            $superContext->pushSpreadKeyValue(...$locales->unwrap(), ...$gc);

            // Replace {code} and {reset_code} in every field (just in case)
            foreach ($superContext as $key => $value) {
                $vcode = $context->get('verification_code', '');
                $rcode = $context->get('reset_code', '');
                $code = ($vcode !== '' && $rcode === '') ? $vcode : $rcode;
                $rpcode = $context->get('reset_pass_code', '');

                if (is_string($value)) {
                    $superContext[$key] = str_replace(['{code}', '{reset_code}'], [$code, $rpcode], $value);
                }
            }

            // Replace locale variable in template name to the actual locale
            $template->template = str_replace('{locale}', $locale, $template->template);

            // Go through the context's title, subject, content and cta title to parse any replacement variable
            // Replacement variables are {xx} format (different to twigs {{xx}} format)

            $replacements = $superContext->get('replacements', []);
            $subject = $template->subject->{$locale};

            foreach ($replacements as $key => $value) {
                foreach ($superContext as $k => $v) {
                    $superContext[$k] = str_replace('{' . $key . '}', $value, $v);
                }
            }

            // Determine what host to use (if no override, use .env url) otherwise use override if allowed
            $request = new Request();
            $override = $request->header('x-domain-override');
            $host = env('SITE_URL');

            if ($override !== '') {
                $allowed = setting('emails.overrides', new Collection(['allow' => false, 'acceptedDomains' => []]))->unwrap();

                if ($allowed['allow'] && in_array($override, $allowed['acceptedDomains'], true)) {
                    if (str_contains($override, 'localhost')) {
                        $host = 'http://' . $override;
                    } else {
                        $host = 'https://' . $override;
                    }
                }
            }

            // Replace {host}
            foreach ($superContext as $key => $value) {
                if (is_string($value)) {
                    $superContext[$key] = str_replace('{host}', $host, $value);
                }
            }

            $superContext->pushKeyValue('host', $host);

            return $this
                ->fromWithName($settings->get('fromName.' . $locale), $settings->get('from'))
                ->subject($subject)
                ->template($template->template, $superContext);
        }

        throw new EmailException("Cannot find the email '{$slug}' from database, please make sure it's not a typo", 0404);
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
     * @deprecated
     *
     */
    public function useEmailVersion1(string $slug, string $locale, Collection|array $context = []): static
    {
        if ($slug === 'test') {
            $settings = setting('emails', []);

            if (is_array($context)) {
                $context = new Collection($context);
            }

            return $this
                ->from($settings->get('from'))
                ->subject('SailCMS Test')
                ->template('test', $context);
        }

        $template = EmailDeprecated::getBySlug($slug);

        if ($template) {
            $settings = setting('emails', []);

            if (is_array($context)) {
                $context = new Collection($context);
            }

            $providedContent = new Collection([
                'email_subject' => $template->subject->{$locale},
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

            // cta_link is present and reset_code. Replace {code} in link for code
            if ($context->get('reset_code', null) !== null) {
                $superContext->setfor('cta_link', str_replace(
                    '{code}',
                    $context->get('reset_code', ''),
                    $superContext->get('cta_link')
                ));
            }

            // cta_link is present and reset_pass_code. Replace {reset_code} in link for code
            if ($context->get('reset_pass_code', null) !== null) {
                $superContext->setfor('cta_link', str_replace(
                    '{reset_code}',
                    $context->get('reset_pass_code', ''),
                    $superContext->get('cta_link')
                ));
            } else {
                $link = $superContext->get('cta_link');
                $superContext->setFor('cta_link', str_replace('{reset_code}', '', $link));
            }

            // Replace locale variable in template name to the actual locale
            $template->template = str_replace('{locale}', $locale, $template->template);

            // Go through the context's title, subject, content and cta title to parse any replacement variable
            // Replacement variables are {xx} format (different to twigs {{xx}} format)

            $title = $superContext->get('email_title');

            $content = $superContext->get('email_content');
            $cta = $superContext->get('cta_link');
            $cta_title = $superContext->get('cta_title');

            $replacements = $superContext->get('replacements', []);

            $subject = $template->subject->{$locale};
            foreach ($replacements as $key => $value) {
                $title = str_replace('{' . $key . '}', $value, $title);
                $content = str_replace('{' . $key . '}', $value, $content);
                $cta = str_replace('{' . $key . '}', $value, $cta);
                $cta_title = str_replace('{' . $key . '}', $value, $cta_title);
                $subject = str_replace('{' . $key . '}', $value, $subject);
            }

            // Determine what host to use (if no override, use .env url) otherwise use override if allowed
            $request = new Request();
            $override = $request->header('x-domain-override');
            $host = env('SITE_URL');

            if ($override !== '') {
                $allowed = setting('emails.overrides', new Collection(['allow' => false, 'acceptedDomains' => []]))->unwrap();

                if ($allowed['allow'] && in_array($override, $allowed['acceptedDomains'], true)) {
                    if (str_contains($override, 'localhost')) {
                        $host = 'http://' . $override;
                    } else {
                        $host = 'https://' . $override;
                    }
                }
            }

            $superContext->setFor('email_title', $title);
            $superContext->setFor('email_content', $content);
            $superContext->setFor('cta_link', str_replace('{host}', $host, $cta));
            $superContext->setFor('cta_title', $cta_title);
            $superContext->pushKeyValue('host', $host);

            return $this
                ->fromWithName($settings->get('fromName.' . $locale), $settings->get('from'))
                ->subject($subject)
                ->template($template->template, $superContext);
        }

        throw new EmailException("Cannot find the email '{$slug}' from database, please make sure it's not a typo", 0404);
    }
}