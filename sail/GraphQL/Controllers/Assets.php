<?php

namespace SailCMS\GraphQL\Controllers;

use GraphQL\Type\Definition\ResolveInfo;
use ImagickException;
use League\Flysystem\FilesystemException;
use SailCMS\Assets\Transformer;
use SailCMS\Collection;
use SailCMS\Database\Model;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\FileException;
use SailCMS\Errors\PermissionException;
use SailCMS\GraphQL\Context;
use SailCMS\Models\Asset;
use SailCMS\Models\AssetFolder;
use SailCMS\Models\User;
use SailCMS\Types\AssetConfig;
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
     * @throws PermissionException
     *
     */
    public function assets(mixed $obj, Collection $args, Context $context): Listing
    {
        $asset = new Asset();

        $page = $args->get('page', 1);
        $limit = $args->get('limit', 100);
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
     * Get all asset folders
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return Collection
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function assetFolders(mixed $obj, Collection $args, Context $context): Collection
    {
        return AssetFolder::folders();
    }

    /**
     *
     * Get Asset Config
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return AssetConfig
     *
     */
    public function assetConfig(mixed $obj, Collection $args, Context $context): AssetConfig
    {
        $conf = setting('assets');
        $mb = ($conf->get('maxUploadSize') * 1024) * 1024;
        $blacklist = $conf->get('extensionBlackList', Collection::init())->unwrap();
        return new AssetConfig($mb, $blacklist);
    }

    /**
     *
     * Get transform url if exists, full size url otherwise
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return string
     *
     */
    public function assetTransformForId(mixed $obj, Collection $args, Context $context): string
    {
        return Asset::getTransformURL($args->get('id'), $args->get('transform_name'));
    }

    /**
     *
     * Create an asset
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return Asset
     * @throws ImagickException
     * @throws FilesystemException
     * @throws DatabaseException
     * @throws FileException
     *
     */
    public function createAsset(mixed $obj, Collection $args, Context $context): Asset
    {
        $asset = new Asset();
        $src = $args->get('src', '');
        $folder = $args->get('folder', 'root');
        $filename = $args->get('filename', 'name.jpg');

        $uploader = (User::$currentUser) ? (string)User::$currentUser->_id : '';
        return $asset->upload(base64_decode($src), $filename, $folder, $uploader);
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
     * @throws PermissionException
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
     * Remove assets
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return bool
     *
     */
    public function removeAssets(mixed $obj, Collection $args, Context $context): bool
    {
        return (new Asset())->removeAll($args->get('assets', Collection::init()));
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

    /**
     *
     * Create a custom transform using a cropping tool
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
    public function customTransformAsset(mixed $obj, Collection $args, Context $context): string
    {
        return Asset::transformCustom(
            $args->get('id'),
            $args->get('name'),
            base64_decode($args->get('src'))
        );
    }

    /**
     *
     * Move files from one folder to another
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function moveFiles(mixed $obj, Collection $args, Context $context): bool
    {
        return (new Asset())->moveFiles($args->get('ids'), $args->get('folder'));
    }

    /**
     *
     * Create a new folder
     *
     * Result
     * 1 = success
     * 2 = permission denied
     * 3 = folder already exists
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return int
     *
     */
    public function addFolder(mixed $obj, Collection $args, Context $context): int
    {
        return AssetFolder::create($args->get('folder'));
    }

    /**
     *
     * Delete a folder and move all files to given alternative
     *
     * @param  mixed       $obj
     * @param  Collection  $args
     * @param  Context     $context
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function removeFolder(mixed $obj, Collection $args, Context $context): bool
    {
        AssetFolder::delete($args->get('folder'));
        (new Asset())->moveAllFiles(
            $args->get('folder'),
            $args->get('move_to', 'root') // failsafe to root
        );

        return true;
    }

    /**
     *
     * Resolve custom fields
     *
     * @param  mixed        $obj
     * @param  Collection   $args
     * @param  Context      $context
     * @param  ResolveInfo  $info
     * @return mixed
     * @throws DatabaseException
     *
     */
    public function assetResolver(mixed $obj, Collection $args, Context $context, ResolveInfo $info): mixed
    {
        if ($info->fieldName === 'uploader') {
            if ($obj->{$info->fieldName}) {
                return User::get($obj->uploader_id);
            }

            return null;
        }

        return $obj->{$info->fieldName};
    }
}