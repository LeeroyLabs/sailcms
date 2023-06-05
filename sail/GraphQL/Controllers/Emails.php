<?php

namespace SailCMS\GraphQL\Controllers;

use SailCMS\Collection;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\EmailException;
use SailCMS\Errors\PermissionException;
use SailCMS\GraphQL\Context;
use SailCMS\Models\Email;

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
        return (new Email())->getList();
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
        return Email::removeBySlug($args->get('slug'));
    }

    public function testEmail(mixed $obj, Collection $args, Context $context): bool
    {
        return Email::sendTest($args->get('email'));
    }
}