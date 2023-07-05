<?php

namespace SailCMS\GraphQL\Controllers;

use GraphQL\Type\Definition\ResolveInfo;
use SailCMS\Collection;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\EntryException;
use SailCMS\Errors\PermissionException;
use SailCMS\GraphQL\Context;
use SailCMS\Models\Entry;
use SailCMS\Models\EntryLayout;
use SailCMS\Models\EntryType;
use SailCMS\Types\LocaleField;

class EntryLayouts
{
    /**
     *
     * Get an entry layout by id
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return array|null
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function entryLayout(mixed $obj, Collection $args, Context $context): ?array
    {
        $entryLayoutId = $args->get('id');

        $entryLayoutModel = new EntryLayout();
        $entryLayout = $entryLayoutModel->one([
            '_id' => $entryLayoutId
        ]);

        return $entryLayout?->simplify();
    }

    /**
     *
     * Get all entry layouts
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return array|null
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function entryLayouts(mixed $obj, Collection $args, Context $context): ?array
    {
        $entryLayouts = Collection::init();
        $result = (new EntryLayout())->getAll() ?? [];

        (new Collection($result))->each(function ($key, $entryLayout) use ($entryLayouts) {
            /**
             * @var EntryLayout $entryLayout
             */
            $entryLayouts->push($entryLayout->simplify());
        });

        return $entryLayouts->unwrap();
    }

    /**
     *
     * Create an entry layout
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return array|null
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    public function createEntryLayout(mixed $obj, Collection $args, Context $context): ?array
    {
        $titles = $args->get('titles');
        $graphqlSchema = $args->get('schema');
        $slug = $args->get('slug');

        $titles = new LocaleField($titles->unwrap());

        $entryLayoutModel = new EntryLayout();
        return $entryLayoutModel->create($titles, $graphqlSchema, $slug)->simplify();
    }

    /**
     *
     * Delete an entry layout
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    public function deleteEntryLayout(mixed $obj, Collection $args, Context $context): bool
    {
        $id = $args->get('id');
        $soft = $args->get('soft', true);

        return (new EntryLayout())->delete($id, $soft);
    }

    /**
     *
     * EntryLayout resolver
     *
     * @param  mixed        $obj
     * @param  Collection   $args
     * @param  Context      $context
     * @param  ResolveInfo  $info
     * @return mixed
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    public function entryLayoutResolver(mixed $obj, Collection $args, Context $context, ResolveInfo $info): mixed
    {
        $obj = (object)$obj;

        if ($info->fieldName === 'used_by') {
            return EntryType::getCountByLayout($obj->_id);
        }

        if ($info->fieldName === 'record_count') {
            $types = EntryType::getTypesUsingLayout($obj->_id);
            return Entry::countAllThatAre($types);
        }

        return $obj->{$info->fieldName};
    }
}