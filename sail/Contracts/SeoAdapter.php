<?php

namespace SailCMS\Contracts;

use MongoDB\BSON\ObjectId;
use SailCMS\Types\Seo\Element;

/**
 *
 * @property string $title
 * @property string $description
 * @property string $image
 * @property string $imageTags
 * @property string $robots
 * @property string $robotsTag
 * @property string $sitemap
 * @property string $facebook
 * @property string $twitter
 * @property string $x
 * @property string $linkedin
 * @property string $social
 *
 */
interface SeoAdapter
{
    public function title(): Element;

    public function description(): Element;

    public function image(): Element;

    public function robots(): Element;
}