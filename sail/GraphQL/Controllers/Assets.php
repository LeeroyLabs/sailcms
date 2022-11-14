<?php

namespace SailCMS\GraphQL\Controllers;

use ImagickException;
use League\Flysystem\FilesystemException;
use SailCMS\Assets\Transformer;
use SailCMS\Collection;
use SailCMS\Database\Model;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\FileException;
use SailCMS\GraphQL\Context;
use SailCMS\Models\Asset;
use SailCMS\Types\Listing;

class Assets
{
    /**
     *
     * Get an asset by id
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return Asset|null
     * @throws DatabaseException
     *
     */
    public function asset(mixed $obj, Collection $args, Context $context): ?Asset
    {
        return Asset::getById($args->get('id'));
    }

    /**
     *
     * Get a list of assets
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return Listing
     * @throws DatabaseException
     * @throws ACLException
     *
     */
    public function assets(mixed $obj, Collection $args, Context $context): Listing
    {
        $asset = new Asset();

        $page = $args->get('page');
        $limit = $args->get('limit');
        $search = $args->get('search', '');
        $folder = $args->get('folder', 'root');
        $sort = $args->get('sort', 'name');
        $direction = strtolower($args->get('direction', 'ASC'));

        $direction = match ($direction) {
            'desc' => Model::SORT_DESC,
            default => Model::SORT_ASC
        };

        return $asset->getList($page, $limit, $folder, $search, $sort, $direction);
    }

    /**
     *
     * Create an asset
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return string
     * @throws ImagickException
     * @throws FilesystemException
     * @throws DatabaseException
     * @throws FileException
     *
     */
    public function createAsset(mixed $obj, Collection $args, Context $context): string
    {
        $asset = new Asset();
        $src = $args->get('src', '');
        $filename = $args->get('filename', 'name.jpg');

        return $asset->upload(base64_decode($src), $filename);
    }

    /**
     *
     * Update asset title
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return bool
     * @throws DatabaseException
     * @throws ACLException
     *
     */
    public function updateAssetTitle(mixed $obj, Collection $args, Context $context): bool
    {
        $asset = Asset::getById($args->get('id'));

        if ($asset) {
            return $asset->update($args->get('locale'), $args->get('title'));
        }

        return false;
    }

    /**
     *
     * Delete an asset
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     *
     */
    public function deleteAsset(mixed $obj, Collection $args, Context $context): bool
    {
        $asset = Asset::getById($args->get('id'));

        if ($asset) {
            return $asset->remove();
        }

        return false;
    }

    /**
     *
     * Transform an asset and return the url
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return string
     * @throws DatabaseException
     * @throws FilesystemException
     * @throws ImagickException
     *
     */
    public function transformAsset(mixed $obj, Collection $args, Context $context): string
    {
        $id = $args->get('id');
        $size = $args->get('size');
        $name = $args->get('name');

        return Asset::transformById(
            $id,
            $name,
            $size->get('width', null),
            $size->get('height', null),
            $size->get('crop', Transformer::CROP_CC)
        );
    }
}