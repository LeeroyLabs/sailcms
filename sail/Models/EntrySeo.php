<?php

namespace SailCMS\Models;

use SailCMS\Collection;
use SailCMS\Contracts\Castable;
use SailCMS\Database\Model;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\EntryException;
use SailCMS\Errors\PermissionException;
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
    protected string $collection = 'entry_seo';
    protected string $permissionGroup = 'entryseo';
    protected array $casting = [

    ];

    public const DATABASE_ERROR = '5100: Exception when "%s" an entry seo.';
    public const DOES_NOT_EXISTS = '5101: Entry SEO with entry id "%s" does not exist.';

//    public function castFrom(): mixed
//    {
//        // TODO: Implement castFrom() method.
//    }
//
//    public function castTo(mixed $value): mixed
//    {
//        // TODO: Implement castTo() method.
//    }

    public function toGraphQL(): array
    {
        // TODO add social data
        return [
            'entry_seo_id' => $this->_id, // TODO is it useful ?
            'title' => $this->title,
            'description' => $this->description,
            'keywords' => $this->keywords,
        ];
    }

    /**
     *
     * Get SEO with an entry id
     *
     * @param string $entryId
     * @param bool $api
     * @return EntrySeo|null
     * @throws DatabaseException
     * @throws ACLException
     * @throws PermissionException
     *
     */
    public function getByEntryId(string $entryId, bool $api = true): ?EntrySeo
    {
        if ($api) {
            $this->hasPermissions(true);
        }

        $entrySeo = $this->findOne(['entry_id' => $entryId])->exec();

        return isset($entrySeo->_id) ? $entrySeo : null;
    }

    /**
     *
     * Get or create SEO with an entry id
     *
     * @param string $entryId
     * @param string $title
     * @return EntrySeo
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    public function getOrCreateByEntryId(string $entryId, string $title): EntrySeo
    {
        $entrySeo = $this->getByEntryId($entryId);

        if (!$entrySeo) {
            $entrySeo = $this->createWithoutPermission($entryId, $title);
        }

        return $entrySeo;
    }

    /**
     *
     * Create or update SEO for a given entry id
     *
     * @param string $entryId
     * @param string $title
     * @param ?Collection $data
     * @return EntrySeo
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    public function createOrUpdate(string $entryId, string $title, ?Collection $data = null): EntrySeo
    {
        $this->hasPermissions();

        $entrySeo = $this->getByEntryId($entryId);

        if (!$data) {
            $data = Collection::init();
        }

        if (!$entrySeo) {
            $description = $data->get('description');
            $keywords = $data->get('keywords');
            $robots = $data->get('robots');
            $sitemap = $data->get('sitemap');
            $default_image = $data->get('default_image');
            $social_metas = $data->get('social_metas', []);

            $entrySeo = $this->createWithoutPermission($entryId, $title, $description, $keywords, $robots, $sitemap, $default_image, $social_metas);
        } else {
            $data->pushKeyValue('title', $title);
            $updated = $this->updateWithoutPermission($entrySeo->_id, $data);

            if ($updated) {
                // Fake new data to avoid a query
                $data->each(function ($property, $value) use (&$entrySeo) {
                    $entrySeo->{$property} = $value;
                });
            }
        }

        return $entrySeo;
    }

    /**
     *
     * Delete entry SEO data for a given entry id
     *
     * @param string $entryId
     * @param bool $api
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    public function deleteByEntryId(string $entryId, bool $api = true): bool
    {
        if ($api) {
            $this->hasPermissions();
        }

        $entrySeo = $this->getByEntryId($entryId, $api);
        if (!$entrySeo) {
            throw new EntryException(sprintf(self::DOES_NOT_EXISTS, $entryId));
        }

        try {
            $qtyDeleted = $this->deleteById($entrySeo->_id);
        } catch (DatabaseException $exception) {
            throw new EntryException(sprintf(self::DATABASE_ERROR, 'deleting'));
        }

        return $qtyDeleted === 1;
    }

    /**
     *
     * Create entry SEO data without permission check
     *
     * @param string $entryId
     * @param string $title
     * @param ?string $description
     * @param ?string $keywords
     * @param ?bool $robots
     * @param ?bool $sitemap
     * @param ?string $defaultImage
     * @param ?array $socialMetas
     * @return EntrySeo
     * @throws DatabaseException
     * @throws EntryException
     *
     */
    private function createWithoutPermission(
        string      $entryId,
        string      $title,
        string|null $description = "",
        string|null $keywords = "",
        bool|null   $robots = false,
        bool|null   $sitemap = true,
        string|null $defaultImage = "",
        array|null  $socialMetas = []): EntrySeo
    {
        try {
            $entrySeoId = $this->insert([
                'entry_id' => $entryId,
                'title' => $title,
                'description' => $description,
                'keywords' => $keywords,
                'robots' => $robots,
                'sitemap' => $sitemap,
                'default_image' => $defaultImage,
                'social_metas' => $this->castSocialMetas($socialMetas)
            ]);
        } catch (DatabaseException $exception) {
            throw new EntryException(sprintf(self::DATABASE_ERROR, 'creating') . PHP_EOL . $exception->getMessage());
        }

        return $this->findById($entrySeoId)->exec();
    }

    /**
     *
     * Update entry SEO data without permission check
     *
     * @param string $entrySeoId
     * @param Collection $data
     * @return bool
     * @throws EntryException
     *
     */
    private function updateWithoutPermission(string $entrySeoId, Collection $data): bool
    {
        $update = [];

        if ($data->get('social_metas')) {
            $update['social_metas'] = $this->castSocialMetas($data->get('social_metas'));
        }

        // Property check
        $data->each(function ($key, $value) use (&$update) {
            if (in_array($key, ['title', 'description', 'keywords', 'robots', 'sitemap', 'default_image', 'social_metas'])) {
                $update[$key] = $value;
            }
        });

        try {
            $qtyUpdated = $this->updateOne(['_id' => $this->ensureObjectId($entrySeoId)], [
                '$set' => $update
            ]);
        } catch (DatabaseException $exception) {
            throw new EntryException(sprintf(self::DATABASE_ERROR, 'updating') . PHP_EOL . $exception->getMessage());
        }

        return $qtyUpdated === 1;
    }

    private function castSocialMetas(array|Collection $socialMetas): array {
        if (is_array($socialMetas)) {
            $socialMetas = new Collection($socialMetas);
        }

        $castedSocialMetas = [];
        $socialMetas->each(function($key, $socialMeta) use (&$castedSocialMetas) {
            if ($socialMeta instanceof SocialMeta) {
                $castedSocialMetas[] = $socialMeta->castFrom();
            } // Else it's an invalid value so we ignore it
        });

        return $castedSocialMetas;
    }
}