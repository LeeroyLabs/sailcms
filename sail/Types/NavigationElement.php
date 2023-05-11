<?php

namespace SailCMS\Types;

use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\EntryException;
use SailCMS\Errors\PermissionException;
use SailCMS\Models\EntryPublication;

class NavigationElement
{
    /**
     *
     * Constructor
     *
     * @throws ACLException
     * @throws PermissionException
     * @throws DatabaseException
     * @throws EntryException
     *
     */
    public function __construct(
        public readonly string $label,
        public string          $url,
        public readonly bool   $is_entry = false,
        public readonly string $entry_id = '',
        public readonly bool   $external = false,
        private array          $children = []
    )
    {
        if ($this->is_entry) {
            // We have an entry, find it's current URL
            $publication = (new EntryPublication())->getPublicationByEntryId($this->entry_id, true, false);
            $entryData = $publication->version->entry;

            if ($entryData) {
                $this->url = $entryData->get('url', '');
            }
        }

        foreach ($this->children as $num => $child) {
            $this->children[$num] = new self(
                $child['label'],
                $child['url'],
                $child['is_entry'],
                $child['entry_id'],
                $child['external'],
                $child['children']
            );
        }
    }

    /**
     *
     * Add a NavigationElement at the end of the children array
     *
     * @param NavigationElement $child
     * @return void
     *
     */
    public function addChild(NavigationElement $child): void
    {
        $this->children[] = $child;
    }

    /**
     *
     * Insert an NavigationElement at given position
     *
     * @param NavigationElement $child
     * @param int $index
     * @return void
     *
     */
    public function insertChildAt(NavigationElement $child, int $index): void
    {
        $this->children = array_splice($this->children, $index, 0, $child);
    }

    /**
     *
     * Get the elements children objects
     *
     * @return array
     *
     */
    public function children(): array
    {
        return $this->children;
    }

    /**
     *
     * Simplify the structure for database/graphql
     *
     * @return array
     *
     */
    public function simplify(): array
    {
        $children = [];

        foreach ($this->children as $child) {
            $children[] = $child->simplify();
        }

        return [
            'label' => $this->label,
            'url' => $this->url,
            'is_entry' => $this->is_entry,
            'entry_id' => $this->entry_id,
            'external' => $this->external,
            'children' => $children
        ];
    }
}
