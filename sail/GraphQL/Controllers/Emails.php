<?php

namespace SailCMS\GraphQL\Controllers;

use SailCMS\Collection;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\EmailException;
use SailCMS\Errors\FileException;
use SailCMS\Errors\PermissionException;
use SailCMS\GraphQL\Context;
use SailCMS\Mail;
use SailCMS\Models\Email;
use SailCMS\Models\EmailDeprecated;
use Twig\Error\LoaderError;

class Emails
{
    /**
     *
     * Get an email by id
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return Email|null
     * @throws DatabaseException
     *
     */
    public function email(mixed $obj, Collection $args, Context $context): ?Email
    {
        return (new Email())->getById($args->get('id'));
    }

    /**
     *
     * Get an email by id
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return EmailDeprecated|null
     * @throws DatabaseException
     *
     */
    public function emailV1(mixed $obj, Collection $args, Context $context): ?EmailDeprecated
    {
        return (new EmailDeprecated())->getById($args->get('id'));
    }

    /**
     *
     * Get all the emails
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return Collection
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function emails(mixed $obj, Collection $args, Context $context): Collection
    {
        return (new Email())->getList($args->get('site_id', 'default'));
    }

    /**
     *
     * Get all the emails
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return Collection
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function emailsV1(mixed $obj, Collection $args, Context $context): Collection
    {
        return (new EmailDeprecated())->getList();
    }

    /**
     *
     * List of email templates available
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return Collection
     *
     */
    public function emailTemplates(mixed $obj, Collection $args, Context $context): Collection
    {
        $siteId = $args->get('site_id', 'default');
        return Mail::loadAndParseTemplates($siteId);
    }

    /**
     *
     * Create an email
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws EmailException
     * @throws PermissionException
     *
     */
    public function createEmail(mixed $obj, Collection $args, Context $context): bool
    {
        return (new Email())->create(
            $args->get('name'),
            $args->get('subject'),
            $args->get('fields'),
            $args->get('template'),
            $args->get('site_id', 'default')
        );
    }

    /**
     *
     * Create an email
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws EmailException
     * @throws PermissionException
     *
     */
    public function createEmailV1(mixed $obj, Collection $args, Context $context): bool
    {
        return (new EmailDeprecated())->create(
            $args->get('name'),
            $args->get('subject'),
            $args->get('title'),
            $args->get('content'),
            $args->get('cta'),
            $args->get('cta_title'),
            $args->get('template'),
        );
    }

    /**
     *
     * Create a preview email
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return string
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function createPreviewEmail(mixed $obj, Collection $args, Context $context): string
    {
        return (new Email())->createPreview(
            $args->get('name'),
            $args->get('subject'),
            $args->get('fields'),
            $args->get('template'),
            $args->get('site_id', 'default')
        );
    }

    /**
     *
     * Update an email by id
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function updateEmail(mixed $obj, Collection $args, Context $context): bool
    {
        return Email::updateById(
            $args->get('id'),
            $args->get('name', null),
            $args->get('subject', null),
            $args->get('fields', null),
            $args->get('template', null)
        );
    }

    /**
     *
     * Update an email by id
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function updateEmailV2(mixed $obj, Collection $args, Context $context): bool
    {
        return EmailDeprecated::updateById(
            $args->get('id'),
            $args->get('name', null),
            $args->get('subject', null),
            $args->get('title', null),
            $args->get('content', null),
            $args->get('cta', null),
            $args->get('cta_title', null),
            $args->get('template', null)
        );
    }

    /**
     *
     * Delete an email by id
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function deleteEmail(mixed $obj, Collection $args, Context $context): bool
    {
        return Email::removeById($args->get('id'));
    }

    /**
     *
     * Delete an email by id
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function deleteEmailV2(mixed $obj, Collection $args, Context $context): bool
    {
        return EmailDeprecated::removeById($args->get('id'));
    }

    /**
     *
     * Delete a list of emails
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function deleteEmails(mixed $obj, Collection $args, Context $context): bool
    {
        return Email::removeList($args->get('ids', []));
    }

    /**
     *
     * Delete an email bt slug
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function deleteEmailBySlug(mixed $obj, Collection $args, Context $context): bool
    {
        return Email::removeBySlug($args->get('slug'), $args->get('site_id', 'default'));
    }

    /**
     *
     * Delete an email bt slug
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function deleteEmailBySlugV1(mixed $obj, Collection $args, Context $context): bool
    {
        return EmailDeprecated::removeBySlug($args->get('slug'));
    }

    /**
     *
     * Test Email Configurations
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws EmailException
     * @throws PermissionException
     * @throws FileException
     * @throws LoaderError
     *
     */
    public function testEmail(mixed $obj, Collection $args, Context $context): bool
    {
        return Email::sendTest($args->get('email'));
    }
}