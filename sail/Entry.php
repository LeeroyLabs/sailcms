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

# TODO

class Entry
{
    private EntryModel $model;
    private ?EntryModel $entry = null;
    private ?EntryPublication $entryPublication = null;
    private string $entryTypeHandle;
    private bool $isUpdated = false;

    /**
     * Hide visibility of the constructor to force user to use static method that return an instance instead
     */
    private function __construct()
    {
    }

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
    public function get(string $id): self
    {
        $this->entry = $this->model->getById($id);

        return $this;
    }

    /**
     *
     * Create an entry
     *
     * @param string $locale
     * @param string $title
     * @param bool $isHomepage
     * @param string|null $template
     * @param string|null $siteId
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
    public function create(string $locale, string $title, bool $isHomepage = false, ?string $template = null, ?string $siteId = null): self
    {
        if (!$template) {
            $template = "default/" . $this->entryTypeHandle;
        }

        $this->entry = $this->model->create($isHomepage, $locale, $title, $template, null, ['site_id' => $siteId]);

        return $this;
    }

    public function update(array $data): self
    {
        $errors = $this->model->updateById($this->entry->_id, $data);
        $this->isUpdated = $errors->length == 0;
        $this->entry = $this->model->getById($this->entry->_id);

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
     * Delete the current entry with default as hard delete
     *
     * @param bool $soft
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws FilesystemException
     * @throws JsonException
     * @throws PermissionException
     * @throws SodiumException
     *
     */
    public function delete(bool $soft = false): bool
    {
        return $this->model->delete($this->entry->_id, $soft);
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
    public function published(): bool
    {
        // TODO handle bad usage

        if ($this->entryPublication) {
            return true;
        }

        $publication = (new EntryPublication())->getPublicationByEntryId($this->entry->_id, false, false);
        return isset($publication);
    }

    /**
     *
     * Return the updated boolean to valid if the entry has been updated
     *
     * @return bool
     */
    public function updated(): bool
    {
        return $this->isUpdated;
    }

    /**
     *
     * Return the entry object
     *
     * @return EntryModel
     */
    public function value(): EntryModel
    {
        return $this->entry;
    }

    /**
     *
     * Return entry parent object
     *
     * @return EntryModel|null
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     */
    public function parent(): ?EntryModel
    {
        return $this->entry->getParent();
    }
}