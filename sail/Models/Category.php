<?php

namespace SailCMS\Models;

use SailCMS\Database\Model;
use SailCMS\Errors\DatabaseException;
use SailCMS\Logging\Local;
use SailCMS\Text;
use SailCMS\Types\LocaleField;

class Category extends Model
{
    public LocaleField $name;
    public string $site_id;
    public string $slug;
    public int $order;
    public string $parent_id;

    public function fields(bool $fetchAllFields = false): array
    {
        return ['_id', 'name', 'site_id', 'slug', 'parent_id', 'order'];
    }

    /**
     *
     * update
     * delete
     * list
     *
     */

    /**
     *
     * Create a category
     *
     * @param  LocaleField  $name
     * @param  string       $site_id
     * @param  string       $parent_id
     * @return bool
     * @throws DatabaseException
     *
     */
    public function create(LocaleField $name, string $site_id, string $parent_id = ''): bool
    {
        // Count the total categories based on parent_id being present or not
        if ($parent_id !== '') {
            $count = $this->count(['site_id' => $site_id, 'parent_id' => $parent_id]);
        } else {
            $count = $this->count(['site_id' => $site_id]);
        }

        $slug = Text::slugify($name->en);

        // Check that it does not exist for the site already
        $exists = $this->count(['slug' => $slug, 'site_id' => $site_id]);

        if ($exists > 0) {
            // Oops!
            throw new DatabaseException('Category with this name already exists', 0403);
        }

        // Set the order
        $count++;

        $this->insert([
            'name' => $name,
            'site_id' => $site_id,
            'slug' => $slug,
            'order' => $count,
            'parent_id' => ($parent_id !== '') ? $parent_id : null
        ]);

        return true;
    }
}