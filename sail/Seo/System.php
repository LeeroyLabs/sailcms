<?php

namespace SailCMS\Seo;

use SailCMS\Contracts\SeoAdapter;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\SitemapException;
use SailCMS\Models\Asset;
use SailCMS\Models\Seo;
use SailCMS\Types\Seo\Element;

class System implements SeoAdapter
{
    private Seo $seoData;

    /**
     *
     * Default SEO Title for the site
     *
     * @return Element
     * @throws DatabaseException
     *
     */
    public function title(): Element
    {
        if (empty($this->seoData)) {
            $this->seoData = Seo::getByType('global');
        }

        return new Element($this->seoData->data, 'title', 'title');
    }

    /**
     *
     * Default SEO Description for the site
     *
     * @return Element
     * @throws DatabaseException
     *
     */
    public function description(): Element
    {
        if (empty($this->seoData)) {
            $this->seoData = Seo::getByType('global');
        }

        return new Element($this->seoData->data, 'description', 'description');
    }

    /**
     *
     * Default SEO Image for the site
     *
     * @return Element
     * @throws DatabaseException
     *
     */
    public function image(): Element
    {
        if (empty($this->seoData)) {
            $this->seoData = Seo::getByType('global');
        }

        $asset = Asset::getById($this->seoData->data->image);
        $data = (object)['image' => $asset->url ?? ''];

        return new Element($data, 'image', 'image');
    }

    /**
     *
     * Default SEO robots for the site
     *
     * @return Element
     * @throws DatabaseException
     *
     */
    public function robots(): Element
    {
        if (empty($this->seoData)) {
            $this->seoData = Seo::getByType('global');
        }

        // Generate sitemap and return it
        return new Element($this->seoData->data, 'robots', 'robots');
    }

    /**
     *
     * Regenerate the sitemaps
     *
     * @throws SitemapException
     *
     */
    public function regenerateSiteMap(): void
    {
        $sitemap = new Sitemap();
        $sitemap->buildMaster();
        $sitemap->buildPerType();
    }

    /**
     *
     * Default SEO facebook config
     *
     * @return Element
     * @throws DatabaseException
     *
     */
    public function facebook(): Element
    {
        if (empty($this->seoData)) {
            $this->seoData = Seo::getByType('global');
        }

        $asset = Asset::getById($this->seoData->data->image);
        $this->seoData->data->image = $asset->url;

        // Generate sitemap and return it
        return new Element($this->seoData->data, 'facebook', 'facebook');
    }

    /**
     *
     * Default SEO twitter/x config
     *
     * @return Element
     * @throws DatabaseException
     *
     */
    public function twitter(): Element
    {
        if (empty($this->seoData)) {
            $this->seoData = Seo::getByType('global');
        }

        $asset = Asset::getById($this->seoData->data->image);
        $this->seoData->data->image = $asset->url;

        // Generate sitemap and return it
        return new Element($this->seoData->data, 'twitter', 'twitter');
    }

    /**
     *
     * Default SEO social config
     *
     * @return Element
     * @throws DatabaseException
     *
     */
    public function social(): Element
    {
        if (empty($this->seoData)) {
            $this->seoData = Seo::getByType('global');
        }

        $asset = Asset::getById($this->seoData->data->image);
        $this->seoData->data->image = $asset->url;

        // Generate sitemap and return it
        return new Element($this->seoData->data, 'social', 'social');
    }

    /**
     *
     * Site name position
     *
     * @return string
     * @throws DatabaseException
     *
     */
    public function siteNamePosition(): string
    {
        if (empty($this->seoData)) {
            $this->seoData = Seo::getByType('global');
        }

        return $this->seoData->data->title_position;
    }

    /**
     *
     * Separator character
     *
     * @return string
     * @throws DatabaseException
     *
     */
    public function separatorCharacter(): string
    {
        if (empty($this->seoData)) {
            $this->seoData = Seo::getByType('global');
        }

        return $this->seoData->data->separator;
    }

    /**
     *
     * Site name
     *
     * @return string
     * @throws DatabaseException
     *
     */
    public function sitename(): string
    {
        if (empty($this->seoData)) {
            $this->seoData = Seo::getByType('global');
        }

        return $this->seoData->data->sitename;
    }

    /**
     *
     * Formatted site name (with separator character)
     *
     * @param  string  $pageTitle
     * @return Element
     * @throws DatabaseException
     *
     */
    public function fullSiteTitle(string $pageTitle = ''): Element
    {
        if (empty($this->seoData)) {
            $this->seoData = Seo::getByType('global');
        }

        if ($pageTitle === '') {
            return new Element((object)['title' => $this->seoData->data->sitename], 'title', 'title');
        }

        $title = $pageTitle . ' ' . $this->seoData->data->separator . ' ' . $this->seoData->data->sitename;

        if ($this->seoData->data->title_position === 'left') {
            $title = $this->seoData->data->sitename . ' ' . $this->seoData->data->separator . ' ' . $pageTitle;
        }

        return new Element((object)['title' => $title], 'title', 'title');
    }

    public function gtag(): string
    {
    }

    /**
     *
     * Convenience getters
     *
     * @param  string  $name
     * @return mixed
     * @throws DatabaseException
     *
     */
    public function __get(string $name): mixed
    {
        switch ($name) {
            case 'title':
                return $this->title()->value();

            case 'description':
                return $this->description()->value();

            case 'image':
                return $this->image()->value();

            case 'imageTags':
                return $this->image()->tag();

            case 'robots':
                return $this->robots()->value();

            case 'robotsTag':
                return $this->robots()->tag();

            case 'facebook':
            case 'linkedin':
                return $this->facebook()->tag();

            case 'twitter':
            case 'x':
                return $this->twitter()->tag();

            case 'social':
                return $this->social()->tag();
        }

        return '';
    }
}