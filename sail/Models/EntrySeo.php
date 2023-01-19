<?php

namespace SailCMS\Models;

use SailCMS\Collection;
use SailCMS\Database\Model;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\EntryException;
use SailCMS\Types\SocialMeta;


/**
 *
 * @property string $entry_id
 * @property string $title
 * @property string $description
 * @property string $keywords
 * @property bool $robots = false
 * @property bool $sitemap = true
 * @property string $default_image
 * @property SocialMeta[] $social_metas
 *
 */
class EntrySeo extends Model
{
    public const DATABASE_ERROR = '5100: Exception when "%s" an entry seo.';

    protected string $collection = 'entry_seo';
    protected string $permissionGroup = 'entryseo';

    public function createEmpty(string $entryId, string $title): EntrySeo
    {
        return $this->createWithoutPermission($entryId, $title);
    }

    public function create(string $entryId, string $title, Collection $data): EntrySeo
    {
        $this->hasPermissions();

        $description = $data->get('description');
        $keywords = $data->get('keyworkds');
        $robots = $data->get('robots');
        $sitemap = $data->get('sitemap');
        $default_image = $data->get('default_image');
        $social_metas = $data->get('social_metas');

        return $this->createWithoutPermission($entryId, $title, $description, $keywords, $robots, $sitemap, $default_image, $social_metas);
    }

    private function createWithoutPermission(string $entryId, string $title, string $description = "", string $keywords = "", bool $robots = false, bool $sitemap = true, string $defaultImage = "", array $socialMetas = []): EntrySeo
    {
        try {
            $entrySeoId = $this->insert([
                'title' => $title,
                'description' => $description,
                'keywords' => $keywords,
                'default_image' => $defaultImage,
                'social_metas' => $socialMetas
            ]);
        } catch (DatabaseException $exception) {
            throw new EntryException(sprintf(self::DATABASE_ERROR, 'creating') . PHP_EOL . $exception->getMessage());
        }

        return $this->findById($entrySeoId)->exec();
    }

}