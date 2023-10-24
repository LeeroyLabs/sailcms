<?php

namespace SailCMS\Types\Seo;

readonly class SitemapLocation
{
    public function __construct(public string $location, public string $lastmod, public string $frequency, public float $priority) { }
}