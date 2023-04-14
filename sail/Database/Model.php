<?php

namespace SailCMS\Database;

use Exception;
use JsonException;
use JsonSerializable;
use MongoDB\BSON\ObjectId;
use MongoDB\Collection;
use SailCMS\ACL;
use SailCMS\Cache;
use SailCMS\Database\Traits\ActiveRecord;
use SailCMS\Database\Traits\Debugging;
use SailCMS\Database\Traits\QueryObject;
use SailCMS\Database\Traits\Transforms;
use SailCMS\Database\Traits\Validation;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\PermissionException;
use SailCMS\Models\User;
use SailCMS\Sail;
use SailCMS\Text;

/**
 *
 * @property ObjectId $_id
 * @property string   $id
 *
 * @uses Validation
 * @uses Transforms
 * @uses ActiveRecord
 * @uses QueryObject
 * @uses Debugging
 *
 */
abstract class Model implements JsonSerializable
{
    // Add Validation Feature
    use Validation;

    // Add Transforms
    use Transforms;

    // Add the ActiveRecord features
    use ActiveRecord;

    // Add the QueryObject features
    use QueryObject;

    // Add debugging tools
    use Debugging;

    // Connection and Collection
    protected int $connection = 0;
    protected string $collection = '';

    // Fields and Guards
    protected array $fields = ['*'];
    protected array $guards = [];

    // Automatic Casting of properties 
    protected array $casting = [];

    // Internal properties
    protected array $properties = [];

    // Permission group for the permission checks
    protected string $permissionGroup = '';

    // Sorting
    public const SORT_ASC = 1;
    public const SORT_DESC = -1;

    // The collection object
    private Collection $active_collection;

    /**
     *
     * @throws DatabaseException
     *
     */
    public function __construct()
    {
        // Just in case
        Cache::init();

        if ($this->collection === '') {
            // Setup using name of class
            $name = array_reverse(explode('\\', get_class($this)))[0];
            $name = Text::from($name)->pluralize()->snake()->value();
            $this->collection = $name;
        }

        // Connection to use
        $client = Database::instance($this->connection);

        if (!$client) {
            throw new DatabaseException('Cannot connect to database, please check your DSN.', 0500);
        }

        $this->active_collection = $client->selectCollection(env('database_db', 'sailcms'), $this->collection);
        $this->init();
    }

    /**
     *
     * Set collection and apply it to the collection resource
     *
     * @param  string  $collection
     * @return void
     * @throws DatabaseException
     *
     */
    public function setCollection(string $collection): void
    {
        $client = Database::instance($this->connection);

        $this->collection = $collection;
        $this->active_collection = $client->selectCollection(env('database_db', 'sailcms'), $collection);
    }

    /**
     *
     * Create an instance without doing the classic instance first
     *
     * @return static
     *
     */
    public static function query(): static
    {
        return new static();
    }

    /**
     *
     * Overridable function called on construct
     *
     * @return void
     *
     */
    public function init(): void
    {
        // implemented at model level
    }

    /**
     *
     * Make sure the value given is already an ObjectId or transform it to one
     *
     * @param  string|ObjectId  $id
     * @return ObjectId
     *
     */
    public function ensureObjectId(string|ObjectId $id): ObjectId
    {
        if (is_string($id) && $this->isValidId($id)) {
            return new ObjectId($id);
        }

        return $id;
    }

    /**
     *
     * Ensure that every given id in array is an ObjectId (return the cleaned up array/collection)
     *
     * @param  array|\SailCMS\Collection  $ids
     * @param  bool                       $returnAsArray
     * @return \SailCMS\Collection|array
     *
     */
    public function ensureObjectIds(array|\SailCMS\Collection $ids, bool $returnAsArray = false): \SailCMS\Collection|array
    {
        if (!is_array($ids)) {
            $ids = $ids->unwrap();
        }

        $list = [];

        foreach ($ids as $id) {
            $list[] = $this->ensureObjectId($id);
        }

        if ($returnAsArray) {
            return $list;
        }

        return new \SailCMS\Collection($list);
    }

    /**
     *
     * Check if ID is a valid MongoDB ID
     *
     * @param  string|ObjectId  $id
     * @return bool
     *
     */
    public function isValidId(string|objectId $id): bool
    {
        if ($id instanceof ObjectId) {
            return true;
        }

        try {
            $id = new ObjectId($id);
            return true;
        } catch (Exception) {
            return false;
        }
    }

    /**
     *
     * Get a property dynamically
     *
     * @param  string  $name
     * @return mixed|string|null
     *
     */
    public function __get(string $name)
    {
        if ($name === 'id') {
            if (!empty($this->properties['_id'])) {
                return (string)$this->properties['_id'];
            }

            return '';
        }

        return $this->properties[$name] ?? null;
    }

    /**
     *
     * Set a properties value
     *
     * @param  string  $name
     * @param          $value
     * @return void
     *
     */
    public function __set(string $name, $value): void
    {
        if ($name !== 'id') {
            $this->properties[$name] = $value;
            $this->isDirty = true;
            $this->dirtyFields[] = $name;
        }
    }

    /**
     *
     * Check if a property is set
     *
     * @param  string  $name
     * @return bool
     *
     */
    public function __isset(string $name): bool
    {
        return isset($this->properties[$name]);
    }

    /**
     *
     * Make a value safe for querying. You should never query using a value
     * that is not either a string or number, unless you are sure that it's safe.
     *
     * @param  mixed  $value
     * @return string|array|bool|int|float
     * @throws JsonException
     *
     */
    protected function safe(mixed $value): string|array|bool|int|float
    {
        if (is_scalar($value)) {
            return $value;
        }

        $output = [];

        foreach ($value as $k => $v) {
            $safe_k = str_replace('$', '', $k);
            $output[$safe_k] = $this->safe($v);
        }

        return $output;
    }

    /**
     *
     * A reusable permission checker
     *
     * @param  bool  $read
     * @return void
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    protected function hasPermissions(bool $read = false): void
    {
        if (Sail::isCLI()) {
            return;
        }

        $errorMsg = 'Permission Denied (' . get_class($this) . ')';

        if (!User::$currentUser) {
            throw new PermissionException('0403: Permission Denied', 0403);
        }

        if ($read) {
            if (!ACL::hasPermission(User::$currentUser, ACL::read($this->permissionGroup), ACL::write($this->permissionGroup))) {
                throw new PermissionException('0403: ' . $errorMsg, 0403);
            }
        } elseif (!ACL::hasPermission(User::$currentUser, ACL::write($this->permissionGroup))) {
            throw new PermissionException('0403: ' . $errorMsg, 0403);
        }
    }

    /**
     *
     * Support for json_encode triggering
     *
     * @return array
     *
     */
    public function jsonSerialize(): array
    {
        return $this->toJSON(true);
    }

    /**
     *
     * Fill an instance with the give object or array of data
     *
     * @param  mixed  $doc
     * @return self
     *
     */
    protected static function fill(mixed $doc): self
    {
        return (new static())->transformDocToModel($doc);
    }

    /**
     *
     * Clear all cache keys for the model
     *
     * @return void
     *
     */
    public function clearCacheForModel(): void
    {
        Cache::removeUsingPrefix(Text::from(get_class($this))->snake()->value());
    }

    /**
     *
     * Build a cache key with the given name
     *
     * @param  string  $key
     * @return string
     *
     */
    private function assembleCacheKey(string $key): string
    {
        return Text::from(get_class($this))->snake()->concat($key, ':')->value();
    }
}