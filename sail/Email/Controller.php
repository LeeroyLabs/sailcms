<?php

namespace SailCMS\Email;

use League\Flysystem\FilesystemException;
use SailCMS\Contracts\AppController;
use SailCMS\Debug;
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

        // Prepare context
        $context = [
            'cta_link' => $email->cta->{$locale},
            'cta_title' => $email->cta_title->{$locale},
            'email_content' => $email->content->{$locale},
            'title' => $email->title->{$locale},
            'subject' => $email->subject->{$locale},
            'locale' => $locale
        ];

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

    /**
     *
     * Load third-party UI content
     *
     * @param  string  $appHash
     * @return void
     *
     */
    public function loadThirdPartyApplication(string $appHash): void
    {
        $elements = UI::getNavigationElements();
        $target = null;
        $listing = [];

        foreach ($elements as $key => $list) {
            foreach ($list as $item) {
                $listing[] = $item;
            }
        }

        foreach ($listing as $item) {
            if (hash('sha256', $item->class . $item->method) === $appHash) {
                $target = $item;
            }
        }

        $instance = new $target->class();
        $this->response->template = $instance->{$target->method}();
    }

    private static function processContext(array $context): array
    {
        $keys = array_keys($context);

        if (in_array('verification_code', $keys, true)) {
            $context['cta_link'] = str_replace('{code}', $context['verification_code'], $context['cta_link']);
        }

        if (in_array('reset_code', $keys, true)) {
            $context['cta_link'] = str_replace('{code}', $context['reset_code'], $context['cta_link']);
        }

        if (in_array('reset_pass_code', $keys, true)) {
            $context['cta_link'] = str_replace('{reset_code}', $context['reset_pass_code'], $context['cta_link']);
        } else {
            $context['cta_link'] = str_replace('{reset_code}', '', $context['cta_link']);
        }

        // Go through the context's title, subject, content and cta title to parse any replacement variable
        // Replacement variables are {xx} format (different to twigs {{xx}} format)

        $replacements = $context['replacements'] ?? [];

        foreach ($replacements as $key => $value) {
            $context['title'] = str_replace('{' . $key . '}', $value, $context['title']);
            $context['email_content'] = str_replace('{' . $key . '}', $value, $context['email_content']);
            $context['cta_link'] = str_replace('{' . $key . '}', $value, $context['cta_link']);
            $context['cta_title'] = str_replace('{' . $key . '}', $value, $context['cta_title']);
            $context['subject'] = str_replace('{' . $key . '}', $value, $context['subject']);
        }

        return $context;
    }
}