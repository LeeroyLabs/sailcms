<?php

namespace SailCMS\Models;

use SailCMS\Collection;
use SailCMS\Database\Model;
use SailCMS\Errors\DatabaseException;

class EntryTypeLayout extends Model
{
    public string $title;
    public Collection $content;

    const LAYOUT_TITLE_SUFFIX = "layout";

    public function fields(bool $fetchAllFields = false): array
    {
        return ['_id', 'title', 'content'];
    }

    /**
     * Create an empty entry type layout and return his id
     *
     * @param  string  $entryTypeTitle
     * @return string
     * @throws DatabaseException
     */
    public function createEmpty(string $entryTypeTitle): string
    {
        return $this->insert([
            'title' => $this->titleFromEntryTitle($entryTypeTitle),
            'content' => new Collection([])
        ]);
    }

    /**
     * Return an entry type layout title with a given entry type title
     *
     * @param  string  $entryTypeTitle
     * @return string
     */
    private function titleFromEntryTitle(string $entryTypeTitle): string
    {
        return $entryTypeTitle . ' ' . static::LAYOUT_TITLE_SUFFIX;
    }
}