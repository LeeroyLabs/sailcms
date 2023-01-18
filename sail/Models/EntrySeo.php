<?php

namespace SailCMS\Models;

use SailCMS\Collection;
use SailCMS\Database\Model;
use SailCMS\Types\SocialMeta;


/**
 *
 * @property string $title
 * @property string $description
 * @property string $keywords
 * // * @property string $canonical // is it necessary ?
 * @property Collection $alternates // HOW WE GENERATE THAT aka DEAL WITH THAT ?
 * @property string $default_image
 * @property SocialMeta[] $social_metas
 *
 */
class EntrySeo extends Model
{
    protected string $collection = 'entry_seo';
    protected string $permissionGroup = 'entryseo';

    public function createEmpty(string $title): EntrySeo
    {
        return $this->createWithoutPermission($title);
    }

    public function create(string $title): EntrySeo
    {
        $this->hasPermissions();

        return $this->createWithoutPermission($title);
    }

    private function createWithoutPermission(string $title, string $description = "", string $keywords = "", string $alternates = ""): EntrySeo
    {
        return $this;
    }
}