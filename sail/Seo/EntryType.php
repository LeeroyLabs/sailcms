<?php

namespace SailCMS\Seo;

use MongoDB\BSON\ObjectId;
use SailCMS\Contracts\SeoAdapter;
use SailCMS\Types\Seo\Element;

class EntryType implements SeoAdapter
{

    /**
     * @param  string|ObjectId  $id
     * @return mixed
     */
    public function get(ObjectId|string $id): mixed
    {
        // TODO: Implement get() method.
    }

    /**
     * @param  string|ObjectId  $id
     * @param  mixed            $data
     * @return bool
     */
    public function set(ObjectId|string $id, mixed $data): bool
    {
        // TODO: Implement set() method.
    }

    /**
     * @return Element
     */
    public function title(): Element
    {
        // TODO: Implement title() method.
    }

    /**
     * @return Element
     */
    public function description(): Element
    {
        // TODO: Implement description() method.
    }

    /**
     * @return Element
     */
    public function image(): Element
    {
        // TODO: Implement image() method.
    }

    /**
     * @return Element
     */
    public function robots(): Element
    {
        // TODO: Implement robots() method.
    }

    /**
     * @return Element
     */
    public function sitemap(): Element
    {
        // TODO: Implement sitemap() method.
    }

    /**
     * @return string
     */
    public function meta(): string
    {
        // TODO: Implement meta() method.
    }
}