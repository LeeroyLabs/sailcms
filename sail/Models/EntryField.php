<?php

namespace SailCMS\Models;

use MongoDB\BSON\ObjectId;
use SailCMS\Collection;
use SailCMS\Contracts\Castable;
use SailCMS\Database\Model;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\EntryException;
use SailCMS\Errors\PermissionException;
use SailCMS\Types\LocaleField;
use stdClass;

/**
 *
 * @property string       $key
 * @property string       $name
 * @property LocaleField  $label
 * @property ?LocaleField $placeholder
 * @property ?LocaleField $explain
 * @property bool         $repeatable # todo add searchable
 * @property string       $validation
 * @property bool         $required
 * @property string       $type
 * @property stdClass     $config
 *
 */
class EntryField extends Model implements Castable
{
    /* Errors */
    public const DATABASE_ERROR = '6100: Exception when %s an entry field.';
    public const KEY_ERROR = '6101: The key "%s" is invalid or already used.';
    public const DOES_NOT_EXIST = '6102: The entry field with key "%s" does not exist.';
    public const MISSING_PARAM_FOR_DELETE = '6103: Must give an id or a key to delete an entry field.';
    public const CANNOT_DELETE = '6104: Cannot delete the entry field because it is used by some entry layout.';

    /* Content errors */
    public const FIELD_REQUIRED = '6105: The field %s is required';

    protected string $collection = 'entry_fields';
    protected string $permissionGroup = 'entryfields';
    protected array $casting = [
        'label' => LocaleField::class,
        'placeholder' => LocaleField::class,
        'explain' => LocaleField::class
    ];

    public static function availableProperties(): array
    {
        return [
            'name',
            'label',
            'placeholder',
            'explain',
            'repeatable',
            'validation',
            'required',
            'type',
            'config'
        ];
    }

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
     * @param  array|null  $query
     * @return Collection
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function getList(?array $query = null): Collection
    {
        $this->hasPermissions(true);
        return new Collection($this->find($query ?? [])->sort(['name' => 1])->exec());
    }

    /**
     *
     * Validate key for entry field
     *
     * @param  string|null  $key
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function validateKey(?string $key = null): bool
    {
        if (!is_string($key)) {
            $key = $this->key;
        }

        // Format validation
        preg_match("/^[a-zA-Z0-9_]+$/", $key, $matches);
        if (($matches && count($matches) < 1) || !$matches) {
            return false;
        }

        // Presence validation
        if (self::getByKey($key)) {
            return false;
        }

        return true;
    }

    /**
     *
     * Create entry field
     *
     * @param  Collection  $args
     * @return EntryField|null
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     *
     */
    public function create(Collection $args): ?EntryField
    {
        $this->hasPermissions();

        $toCreate = Collection::init();
        $args->each(function ($key, $value) use (&$toCreate) {
            if (in_array($key, self::availableProperties())) {
                $toCreate->setFor($key, $value);
            }
        });

        // Default properties that has not been sets
        if (!$toCreate->get('explain')) {
            $args->setFor('explain', '');
        }
        if (!$toCreate->get('placeholder')) {
            $args->setFor('placeholder', '');
        }
        if ($toCreate->get('repeatable') === null) {
            $args->setFor('repeatable', false);
        }
        if (!$toCreate->get('validation')) {
            $args->setFor('validation', '');
        }
        if (!$toCreate->get('config')) {
            $args->setFor('config', '');
        }

        /**
         * @var EntryField $entryField
         */
        $entryField = $this::fill($args);

        if (!$entryField->validateKey()) {
            throw new EntryException(sprintf(self::KEY_ERROR, $entryField->key), 6101);
        }

        if (!$entryField->save()) {
            throw new EntryException(sprintf(self::DATABASE_ERROR, 'creating'), 6100);
        }

        return $entryField;
    }

    /**
     *
     * Update an entry field for a given id
     *
     * @param  string      $id
     * @param  Collection  $args
     * @return bool
     * @throws DatabaseException
     *
     */
    public function update(string $id, Collection $args): bool
    {
        $result = true;
        $toUpdate = [];


        $args->each(function ($key, $value) use (&$toUpdate) {
            if (in_array($key, self::availableProperties())) {
                $toUpdate[$key] = $value;
            }
        });

        if (count($toUpdate) > 0) {
            $result = $this->updateOne(['_id' => $this->ensureObjectId($id)], [
                '$set' => $toUpdate
            ]);
        }

        return (bool)$result;
    }

    /**
     *
     * Delete by id or key
     *
     * @param  string|null  $id
     * @param  string|null  $key
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws EntryException
     * @throws PermissionException
     */
    public function deleteByIdOrKey(?string $id = null, ?string $key = null): bool
    {
        $this->hasPermissions();
        $result = true;

        // Validate the presence of id or key
        if (!$id && !$key) {
            throw new EntryException(self::MISSING_PARAM_FOR_DELETE);
        }

        // Acquire entry field id if key is passed
        $entryFieldId = $id;
        if ($key) {
            $entryField = self::getByKey($key);
            if (!$entryField) {
                throw new EntryException(sprintf(self::DOES_NOT_EXIST, $key));
            }
            $entryFieldId = $entryField->_id;
        }

        // Check if entry field is used
        if (EntryLayout::countUsedEntryField((string)$entryFieldId) > 0) {
            throw new EntryException(self::CANNOT_DELETE);
        }

        if ($entryFieldId) {
            $result = $this->deleteById($entryFieldId);
        }

        return (bool)$result;
    }

    /**
     *
     * Delete many entry fields by id
     *
     * @param  Collection|array  $ids
     * @return int
     * @throws DatabaseException
     *
     */
    public function deleteManyByIds(Collection|array $ids): int
    {
        if ($ids instanceof Collection) {
            $ids = $ids->unwrap();
        }
        $ids = $this->ensureObjectIds($ids, true);

        return $this->deleteMany(['_id' => ['$in' => $ids]]);
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
     * @return Model
     *
     */
    public function castTo(mixed $value): Model
    {
        return self::fill($value);
    }
}