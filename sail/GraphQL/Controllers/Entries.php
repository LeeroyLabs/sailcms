<?php

namespace SailCMS\GraphQL\Controllers;

use SailCMS\Collection;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\EntryException;
use SailCMS\GraphQL\Context;
use SailCMS\Models\Entry;
use SailCMS\Models\EntryType;

class Entries
{
    /**
     *
     * Get an entry by id (MUST TESTS)
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return Entry|null
     * @throws DatabaseException
     * @throws EntryException
     *
     */
    public function entry(mixed $obj, Collection $args, Context $context): ?Entry
    {
        $type = $args->get('typeHandle');
        $id = $args->get('id');

        if ($type) {
            $entryModel = EntryType::getEntryModelByHandle($type);
        } else {
            $entryModel = new Entry();
        }

        return $entryModel->one(['_id' => $id]);
    }
}