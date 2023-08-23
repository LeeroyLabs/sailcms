<?php

namespace SailCMS;

use JsonException;
use League\Flysystem\FilesystemException;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\EntryException;
use SailCMS\Errors\PermissionException;
use SailCMS\Models\Entry as EntryModel;
use SailCMS\Models\EntryPublication;
use SailCMS\Models\EntryType;
use SodiumException;

class Entry
{
    private EntryModel $model;
    private ?EntryModel $entry = null;
    private ?EntryPublication $entryPublication = null;
    private string $entryTypeHandle;


    /**
     *
     * Set the model
     *
     * @param  string  $entryTypeHandle
     * @return self
     * @throws Errors\ACLException
     * @throws Errors\DatabaseException
     * @throws Errors\EntryException
     * @throws Errors\PermissionException
     *
     */
    public static function from(string $entryTypeHandle = EntryType::DEFAULT_HANDLE): self
    {
        $instance = new self();
        $instance->entryTypeHandle = $entryTypeHandle;
        $instance->model = EntryType::getEntryModelByHandle($entryTypeHandle);
        return $instance;
    }

    /**
     *
     * Get an entry with an id
     *
     * @param  string  $id
     * @return self
     * @throws DatabaseException
     *
     */
    public function byId(string $id): self
    {
        $this->entry = $this->model->getById($id);

        return $this;
    }

    /**
     *
     * Create an entry
     *
     * @param  string       $locale
     * @param  string       $title
     * @param  string       $template
     * @param  bool         $isHomepage
     * @param  string|null  $siteId
     * @return self
     * @throws DatabaseException
     * @throws ACLException
     * @throws EntryException
     * @throws PermissionException
     * @throws JsonException
     * @throws FilesystemException
     * @throws SodiumException
     *
     */
    public function create(string $locale, string $title, string $template = '', bool $isHomepage = false, ?string $siteId = null): self
    {
        if (!$template) {
            $template = "default/" . $this->entryTypeHandle;
        }

        $this->entry = $this->model->create($isHomepage, $locale, $title, $template);

        return $this;
    }

    /**
     *
     * Publish the current entry
     *
     * @param  int  $publicationDate
     * @param  int  $expirationDate
     * @return self
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws FilesystemException
     * @throws JsonException
     * @throws PermissionException
     * @throws SodiumException
     *
     */
    public function publish(int $publicationDate, int $expirationDate = 0): self
    {
        $this->model->publish($this->entry->_id, $publicationDate, $expirationDate);

        $this->entryPublication = (new EntryPublication())->getPublicationByEntryId($this->entry->_id);

        return $this;
    }

    /**
     *
     * Check if the current entry is published
     *
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function isPublished(): bool
    {
        // TODO handle bad usage

        if ($this->entryPublication) {
            return true;
        }

        $publication = (new EntryPublication())->getPublicationByEntryId($this->model->_id, false, false);
        return isset($publication);
    }
}