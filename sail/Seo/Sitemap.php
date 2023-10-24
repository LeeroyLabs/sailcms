<?php

namespace SailCMS\Seo;

use Exception;
use SailCMS\Collection;
use SailCMS\Errors\FileException;
use SailCMS\Errors\SitemapException;
use SailCMS\Errors\StorageException;
use SailCMS\Models\Entry as EntryModel;
use SailCMS\Models\EntryType as EntryTypeModel;
use SailCMS\Models\Seo;
use SailCMS\Storage;
use SailCMS\Templating\Engine;
use SailCMS\Types\Seo\SitemapLocation;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class Sitemap
{
    private Storage $store;
    private string $masterPath = '';
    private string $typePath = '';
    private Collection $entryTypes;

    public function __construct()
    {
        $this->store = Storage::on('local');
        $this->masterPath = 'web/sitemap.xml';
        $this->typePath = 'web/sitemaps';

        $this->entryTypes = EntryTypeModel::getAll(true);
    }

    public function buildMaster(): void
    {
        $mapOfMaps = [];

        foreach ($this->entryTypes as $et) {
            $mapOfMaps[] = env('SITE_URL') . '/sitemaps/' . $et->handle . '.xml';
        }

        try {
            $engine = new Engine();
            $content = $engine->render('mastermap', (object)['maps' => $mapOfMaps]);
            $this->store->store('web/sitemap.xml', $content);

            // Create sitemaps folder if missing
            if (!$this->store->exists('web/sitemaps', true)) {
                try {
                    $this->store->createDirectory('web/sitemaps');
                } catch (StorageException $e) {
                    throw new SitemapException($e->getMessage(), 0500);
                }
            }
        } catch (LoaderError|RuntimeError|SyntaxError|StorageException $e) {
            throw new SitemapException($e->getMessage(), 0500);
        }
    }

    public function buildPerType(): void
    {
//        $entryTypes = EntryTypeModel::getAll(true);
//
//        // Create a sitemap for each entry type
//        foreach ($entryTypes as $et) {
//            // 50_000 is the physical limit of sitemaps
//            $entries = EntryModel::getList($et->handle, '', 1, 50_000);
//            $entryList = [];
//
//            foreach ($entries->list as $item) {
//                $entryList = new SitemapLocation($item->)
//            }
//        }
    }
}