<?php

namespace SailCMS\Models;

use SailCMS\Database\BaseModel;
use SailCMS\Types\LocaleField;

class Category extends BaseModel
{
    public LocaleField $name;
    public string $site_id;
    public string $slug;
    public int $order;
    public string $parent_id;

    public function fields(bool $fetchAllFields = false): array
    {
        return ['_id', 'site_id', 'slug', 'parent_id', 'order'];
    }

    /**
     *
     * add
     * update
     * delete
     * list
     *
     *
     */
}