<?php

namespace SailCMS\Types\Seo;

use SailCMS\Locale;
use SailCMS\Sail;

class Element implements \Stringable
{
    public function __construct(private object $elementData, private string $key, private string $type = 'title')
    {
    }

    public function tag(): string
    {
        if (empty($this->elementData->isArticle)) {
            $this->elementData->isArticle = false;
        }

        if (empty($this->elementData->locale)) {
            $this->elementData->locale = Locale::default();
        }

        $locales = include Sail::getWorkingDirectory() . '/config/locales.php';

        switch ($this->type) {
            case 'title':
                break;

            case 'description':
                break;

            case 'facebook':
                $meta = '<meta property="og:type" content="website" />';

                if ($this->elementData->isArticle !== null) {
                    $meta = '<meta property="og:type" content="article" />';
                }

                if ($this->elementData->locale !== 'en') {
                    $meta .= "\n";
                    $meta .= '<meta property="og:locale" content="' . $locales[$this->elementData->locale] . '" />';
                }

                $meta .= "\n";
                $meta .= '<meta property="og:title" content="' . $this->elementData->title . '" />';
                $meta .= "\n";
                $meta .= '<meta property="og:description" content="' . $this->elementData->description . '" />';
                $meta .= "\n";
                $meta .= '<meta property="og:image" content="' . $this->elementData->image . '" />';
                return $meta;

            case 'x':
            case 'twitter':
                $meta = '<meta property="twitter:card" content="website" />';

                if ($this->elementData->isArticle !== null) {
                    $meta = '<meta property="twitter:card" content="article" />';
                }

                $meta .= "\n";
                $meta .= '<meta property="twitter:site" content="' . $this->elementData->twitter_site . '" />';
                $meta .= "\n";
                $meta .= '<meta property="twitter:title" content="' . $this->elementData->title . '" />';
                $meta .= "\n";
                $meta .= '<meta property="twitter:description" content="' . $this->elementData->description . '" />';
                $meta .= "\n";
                $meta .= '<meta property="twitter:image" content="' . $this->elementData->image . '" />';
                return $meta;

            case 'social':
                $meta = '<meta property="og:type" content="website" />';

                if ($this->elementData->isArticle) {
                    $meta = '<meta property="og:type" content="article" />';
                }

                if ($this->elementData->locale !== 'en') {
                    $meta .= "\n";
                    $meta .= '<meta property="og:locale" content="' . $locales[$this->elementData->locale] . '" />';
                }

                $meta .= "\n";
                $meta .= '<meta property="og:title" content="' . $this->elementData->title . '" />';
                $meta .= "\n";
                $meta .= '<meta property="og:description" content="' . $this->elementData->description . '" />';
                $meta .= "\n";
                $meta .= '<meta property="og:image" content="' . $this->elementData->image . '" />';

                // Twitter / X
                $meta .= "\n";

                if ($this->elementData->isArticle) {
                    $meta .= '<meta property="twitter:card" content="article" />';
                } else {
                    $meta .= '<meta property="twitter:card" content="website" />';
                }

                $meta .= "\n";
                $meta .= '<meta property="twitter:site" content="' . $this->elementData->twitter_site . '" />';
                $meta .= "\n";
                $meta .= '<meta property="twitter:title" content="' . $this->elementData->title . '" />';
                $meta .= "\n";
                $meta .= '<meta property="twitter:description" content="' . $this->elementData->description . '" />';
                $meta .= "\n";
                $meta .= '<meta property="twitter:image" content="' . $this->elementData->image . '" />';
                return $meta;

            case 'image':
                $meta = '<meta property="og:image" content="' . $this->elementData->{$this->key} . '" />';
                $meta .= "\n";
                $meta .= '<meta name="twitter:image" content="' . $this->elementData->{$this->key} . '">';
                return $meta;

            case 'robots':
                return '<meta name="robots" content="' . $this->elementData->{$this->key} . '"/>';
        }

        return '';
    }

    public function meta(): string
    {
        return '';
    }

    public function value(): string
    {
        if (!empty($this->elementData->{$this->key})) {
            return $this->elementData->{$this->key};
        }

        return '';
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->elementData->{$this->key};
    }
}