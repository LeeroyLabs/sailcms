<?php

namespace SailCMS\Models;

use MongoDB\BSON\ObjectId;
use SailCMS\Collection;
use SailCMS\Contracts\Castable;
use SailCMS\Database\Model;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\PermissionException;
use SailCMS\Types\LocaleField;
use stdClass;

/**
 *
 * @property string      $key  // TODO must be unique, set from Name or throw errors
 * @property string      $name
 * @property LocaleField $label
 * @property LocaleField $placeholder
 * @property LocaleField $explain
 * @property bool        $repeatable
 * @property string      $validation
 * @property bool        $required
 * @property string      $type
 * @property stdClass    $config
 *
 */
class EntryField extends Model implements Castable
{
    protected string $collection = 'entry_fields';
    protected string $permissionGroup = 'entryfields';

    /**
     *
     * Get a field by its key
     *
     * @param  string  $key
     * @return EntryField|null
     * @throws DatabaseException
     * @throws ACLException
     * @throws PermissionException
     *
     */
    public static function getByKey(string $key): ?EntryField
    {
        (new self())->hasPermissions(true);
        return self::getBy('key', $key);
    }

    /**
     *
     * Get a field by its id
     *
     * @param  string|ObjectId  $id
     * @return EntryField|null
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public static function getById(string|ObjectId $id): ?EntryField
    {
        (new self())->hasPermissions(true);
        return self::getById($id);
    }

    /**
     *
     * Get list of existing fields
     *
     * @return Collection
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function getList(): Collection
    {
        $this->hasPermissions(true);
        return new Collection($this->find([])->sort(['name' => 1])->exec());
    }

    /**
     *
     * Simplify object
     *
     */
    public function castFrom(): array
    {
        return [
            '_id' => $this->_id,
            'key' => $this->key,
            'name' => $this->name,
            'label' => $this->label->castFrom(),
            'placeholder' => $this->placeholder ? $this->placeholder->castFrom() : null,
            'explain' => $this->explain ? $this->explain->castFrom() : null,
            'repeatable' => $this->repeatable,
            'validation' => $this->validation,
            'required' => $this->required,
            'type' => $this->type,
            'config' => $this->config
        ];
    }

    /**
     *
     * Cast simple object/array to EntryField
     *
     * @param  mixed  $value
     * @return mixed
     *
     */
    public function castTo(mixed $value): mixed
    {
        return self::fill($value);
    }
}