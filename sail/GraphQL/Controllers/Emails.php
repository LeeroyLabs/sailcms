<?php

namespace SailCMS\GraphQL\Controllers;

use SailCMS\Collection;
use SailCMS\Debug;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\EmailException;
use SailCMS\Errors\PermissionException;
use SailCMS\GraphQL\Context;
use SailCMS\Models\Email;
use SailCMS\Sail;

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
        $folders = glob(Sail::getTemplateDirectory() . $siteId . '/email/*.twig');

        $list = [];

        foreach ($folders as $folder) {
            $list[] = basename($folder, '.twig');
        }

        return new Collection($list);
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
            $args->get('title'),
            $args->get('content'),
            $args->get('cta'),
            $args->get('cta_title'),
            $args->get('template'),
            $args->get('site_id', 'default')
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
     * @throws EmailException
     * @throws PermissionException
     *
     */
    public function createPreviewEmail(mixed $obj, Collection $args, Context $context): string
    {
        return (new Email())->createPreview(
            $args->get('name'),
            $args->get('subject'),
            $args->get('title'),
            $args->get('content'),
            $args->get('cta'),
            $args->get('cta_title'),
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
            $args->get('title', null),
            $args->get('content', null),
            $args->get('cta', null),
            $args->get('cta_title', null),
            $args->get('template', null),
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

    public function testEmail(mixed $obj, Collection $args, Context $context): bool
    {
        return Email::sendTest($args->get('email'));
    }
}