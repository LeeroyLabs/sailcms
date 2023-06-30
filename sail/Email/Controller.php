<?php

namespace SailCMS\Email;

use League\Flysystem\FilesystemException;
use SailCMS\Collection;
use SailCMS\Contracts\AppController;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\EmailException;
use SailCMS\Errors\FileException;
use SailCMS\Locale;
use SailCMS\Mail;
use SailCMS\Models\Email;
use SailCMS\Sail;
use SailCMS\Types\MailPreviewData;
use SailCMS\UI;

class Controller extends AppController
{
    /**
     *
     * Preview an email
     *
     * @param  string  $name
     * @param  string  $locale
     * @return void
     * @throws EmailException
     * @throws FileException
     * @throws FilesystemException
     * @throws DatabaseException
     *
     */
    public function previewEmail(string $name, string $locale): void
    {
        // Fetch template if it exists
        $email = Email::getBySlug($name);

        if (!$email) {
            // Get by id if name fails
            $email = Email::get($name);
        }

        Locale::setCurrent($locale);

        if (!$email) {
            throw new EmailException('Cannot find email to preview. Please make sure it exists', 0404);
        }

        $context = [
            'subject' => $email->subject->{$locale},
            'locale' => $locale
        ];

        foreach ($email->fields as $field) {
            $context[$field->key] = $field->value->{$locale};
        }

        // Call extra context provider
        $handler = Mail::getRegisteredPreviewHandler($name);

        if ($handler) {
            $data = call_user_func([$handler['class'], $handler['method']], $name, $context);

            if (is_object($data) && get_class($data) === MailPreviewData::class) {
                $context = self::processContext([...$context, ...$data->context]);
                $this->response->setArray($context);

                if ($data->template !== '') {
                    $this->response->template = $data->template;
                } else {
                    // Try to determine the path and file for it
                    $path = Sail::getWorkingDirectory() . '/templates/' . $email->site_id . '/email/' . $email->template;

                    if (file_exists($path . '.twig')) {
                        $this->response->template = $email->site_id . '/email/' . $email->template;
                    } else {
                        throw new FileException(
                            "Cannot find template, please provide it's path using a preview handler. For debugging, trying to find it in {$path}",
                            0404
                        );
                    }
                }
            } else {
                throw new EmailException('A Preview Handler cannot return anything else than a MailPreviewData object', 0400);
            }
        } else {
            $context = self::processContext($context);
            $path = Sail::getWorkingDirectory() . '/templates/' . $email->site_id . '/email/' . $email->template;

            if (file_exists($path . '.twig')) {
                $this->response->template = $email->site_id . '/email/' . $email->template;
            } else {
                throw new FileException(
                    "Cannot find template, please provide it's path using a preview handler. For debugging, trying to find it in {$path}",
                    0404
                );
            }
        }

        $this->response->setArray($context);
    }

    private static function processContext(array $context): array
    {
        $keys = array_keys($context);

        // Replace {code} and {reset_code} in every field (just in case)
        foreach ($context as $key => $value) {
            $vcode = $context['verification_code'] ?? '';
            $rcode = $context['reset_code'] ?? '';
            $code = ($vcode !== '' && $rcode === '') ? $vcode : $rcode;
            $rpcode = $context['reset_pass_code'] ?? '';

            if (is_string($value)) {
                $context[$key] = str_replace(['{code}', '{reset_code}'], [$code, $rpcode], $value);
            }
        }

        // Go through the context's title, subject, content and cta title to parse any replacement variable
        // Replacement variables are {xx} format (different to twigs {{xx}} format)

        $replacements = $context['replacements'] ?? [];

        foreach ($replacements as $key => $value) {
            foreach ($context as $k => $v) {
                $context[$k] = str_replace('{' . $key . '}', $value, $v);
            }
        }
        
        return $context;
    }
}