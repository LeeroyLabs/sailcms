<?php

namespace SailCMS\Seo;

use MongoDB\BSON\ObjectId;
use SailCMS\Contracts\SeoAdapter;
use SailCMS\Errors\DatabaseException;
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

    public function siteNamePosition(): string
    {
        if (empty($this->seoData)) {
            $this->seoData = Seo::getByType('global');
        }

        return $this->seoData->data->title_position;
    }

    public function separatorCharacter(): string
    {
        if (empty($this->seoData)) {
            $this->seoData = Seo::getByType('global');
        }

        return $this->seoData->data->separator;
    }

    public function sitename(): string
    {
        if (empty($this->seoData)) {
            $this->seoData = Seo::getByType('global');
        }

        return $this->seoData->data->sitename;
    }

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
                break;

            case 'robots':
                break;

            case 'sitemap':
                break;

            case 'facebook':
                break;

            case 'twitter':
            case 'x':
                break;

            case 'linkedin':
                break;
        }
    }
}