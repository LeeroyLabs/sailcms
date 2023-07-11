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
use SailCMS\Types\EntryLayoutTab;
use SailCMS\Types\LocaleField;

class EntryLayouts
{
    /**
     *
     * Get an entry layout by slug
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return EntryLayout|null
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function entryLayout(mixed $obj, Collection $args, Context $context): ?EntryLayout
    {
        return (new EntryLayout())->bySlug($args->get('slug'));
    }

    /**
     *
     * Get an entry layout by id
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return EntryLayout|null
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function entryLayoutById(mixed $obj, Collection $args, Context $context): ?EntryLayout
    {
        return (new EntryLayout())->one([
            '_id' => $args->get('id')
        ]);
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
        $result = (new EntryLayout())->getAll() ?? [];
        $entryLayouts = new Collection($result);

        $fieldIds = EntryLayout::getEntryFieldIds($entryLayouts);
        EntryLayout::fetchFields($fieldIds, $entryLayouts);

        return $entryLayouts->unwrap();
    }

    /**
     *
     * Create an entry layout
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return EntryLayout|null
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    public function createEntryLayout(mixed $obj, Collection $args, Context $context): ?EntryLayout
    {
        $titles = $args->get('titles');
        $graphqlSchema = $args->get('schema');
        $slug = $args->get('slug');

        $titles = new LocaleField($titles->unwrap());

        $schema = Collection::init();
        foreach ($graphqlSchema as $tab) {
            $fields = $tab->fields->unwrap() ?? [];

            $schema->push(new EntryLayoutTab($tab->label, $fields));
        }

        $entryLayoutModel = new EntryLayout();
        return $entryLayoutModel->create($titles, $schema, $slug);
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