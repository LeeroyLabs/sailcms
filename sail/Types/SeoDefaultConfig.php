<?php

namespace SailCMS\Types;

use SailCMS\Collection;
use SailCMS\Contracts\Castable;

class SeoDefaultConfig implements Castable
{
    public string $title;
    public string $description;
    public string $keywords;
    public bool $robots;
    public bool $sitemap;
    public string $default_image;
    public SocialMeta $social_metas;

    public function __construct(array $values)
    {
        if ($values['social_metas']) {
            foreach ($values as $key => $value) {
                if ($key === 'social_metas') {
                    $social_metas = new SocialMeta();
                    $this->social_metas = $social_metas->castTo((object)$value);
                }
            }
        }

        $this->title = $values['title'];
        $this->description = $values['description'];
        $this->keywords = $values['keywords'];
        $this->robots = $values['robots'];
        $this->sitemap = $values['sitemap'];
        $this->default_image = $values['default_image'];
    }

    public function castFrom(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'keywords' => $this->keywords,
            'robots' => $this->robots,
            'sitemap' => $this->sitemap,
            'default_image' => $this->default_image,
            'social_metas' => $this->social_metas
        ];
    }

    public function castTo(mixed $value): SeoDefaultConfig
    {
        return new self($value);
    }
}