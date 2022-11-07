<?php

namespace SailCMS\Models;

use MongoDB\BSON\ObjectId;
use SailCMS\ACL;
use SailCMS\Collection;
use SailCMS\Database\BaseModel;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\EmailException;
use SailCMS\Text;
use SailCMS\Types\LocaleField;

class Email extends BaseModel
{
    public string $name;
    public string $slug;
    public LocaleField $subject;
    public LocaleField $title;
    public LocaleField $content;
    public LocaleField $cta;
    public LocaleField $cta_title;
    public string $template;
    public int $created_at;
    public int $last_modified;

    public function fields(bool $fetchAllFields = false): array
    {
        return [
            '_id',
            'name',
            'slug',
            'subject',
            'title',
            'content',
            'cta',
            'cta_title',
            'template',
            'created_at',
            'last_modified'
        ];
    }

    protected function processOnFetch(string $field, mixed $value): mixed
    {
        $fields = ['subject', 'title', 'content', 'cta', 'cta_title'];

        if (in_array($field, $fields)) {
            return new LocaleField($value);
        }

        return $value;
    }

    /**
     *
     * Get an email by slug
     *
     * @param  string  $slug
     * @return Email|null
     * @throws DatabaseException
     *
     */
    public static function getBySlug(string $slug): ?Email
    {
        $instance = new static();
        return $instance->findOne(['slug' => $slug])->exec();
    }

    /**
     *
     * Get an email by id
     *
     * @param  ObjectId|string  $id
     * @return Email|null
     * @throws DatabaseException
     *
     */
    public function getById(ObjectId|string $id): ?Email
    {
        return $this->findById($id)->exec();
    }

    /**
     *
     * Get a list of all emails
     *
     * @return Collection
     * @throws ACLException
     * @throws DatabaseException
     *
     */
    public function getList(): Collection
    {
        if (ACL::hasPermission(User::$currentUser, ACL::read('emails'))) {
            return new Collection($this->find([])->exec());
        }

        return new Collection([]);
    }

    /**
     *
     * Create a new email
     *
     * @param  string                        $name
     * @param  LocaleField|Collection|array  $subject
     * @param  LocaleField|Collection|array  $title
     * @param  LocaleField|Collection|array  $content
     * @param  LocaleField|Collection|array  $cta
     * @param  LocaleField|Collection|array  $cta_title
     * @param  string                        $template
     * @return bool
     * @throws EmailException
     * @throws DatabaseException
     * @throws ACLException
     *
     */
    public function create(
        string $name,
        LocaleField|Collection|array $subject,
        LocaleField|Collection|array $title,
        LocaleField|Collection|array $content,
        LocaleField|Collection|array $cta,
        LocaleField|Collection|array $cta_title,
        string $template
    ): bool {
        if (ACL::hasPermission(User::$currentUser, ACL::write('emails'))) {
            $slug = Text::deburr(Text::snakeCase($name));
            $record = $this->findOne(['slug' => $slug])->exec();

            if ($record) {
                throw new EmailException('Email with this name already exists, please change the name', 0403);
            }

            $this->insert([
                'name' => $name,
                'slug' => $slug,
                'subject' => $subject,
                'title' => $title,
                'content' => $content,
                'cta' => $cta,
                'cta_title' => $cta_title,
                'template' => $template,
                'created_at' => time(),
                'last_modified' => time()
            ]);

            return true;
        }

        return false;
    }

    /**
     *
     * Update an email (instance version)
     *
     * @param  string|null                        $name
     * @param  LocaleField|Collection|array|null  $subject
     * @param  LocaleField|Collection|array|null  $title
     * @param  LocaleField|Collection|array|null  $content
     * @param  LocaleField|Collection|array|null  $cta
     * @param  LocaleField|Collection|array|null  $cta_title
     * @param  string|null                        $template
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     */
    public function update(
        string|null $name = null,
        LocaleField|Collection|array|null $subject = null,
        LocaleField|Collection|array|null $title = null,
        LocaleField|Collection|array|null $content = null,
        LocaleField|Collection|array|null $cta = null,
        LocaleField|Collection|array|null $cta_title = null,
        string|null $template = null
    ): bool {
        return static::updateBy(['_id' => $this->_id], $name, $subject, $title, $content, $cta, $cta_title, $template);
    }

    /**
     *
     * Update an email by id (static version)
     *
     * @param  ObjectId|string                    $id
     * @param  string|null                        $name
     * @param  LocaleField|Collection|array|null  $subject
     * @param  LocaleField|Collection|array|null  $title
     * @param  LocaleField|Collection|array|null  $content
     * @param  LocaleField|Collection|array|null  $cta
     * @param  LocaleField|Collection|array|null  $cta_title
     * @param  string|null                        $template
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     *
     */
    public static function updateById(
        ObjectId|string $id,
        string|null $name = null,
        LocaleField|Collection|array|null $subject = null,
        LocaleField|Collection|array|null $title = null,
        LocaleField|Collection|array|null $content = null,
        LocaleField|Collection|array|null $cta = null,
        LocaleField|Collection|array|null $cta_title = null,
        string|null $template = null
    ): bool {
        $instance = new static();
        $id = $instance->ensureObjectId($id);
        return static::updateBy(['_id' => $id], $name, $subject, $title, $content, $cta, $cta_title, $template);
    }

    /**
     *
     * Update by slug
     *
     * @param  string                             $slug
     * @param  string|null                        $name
     * @param  LocaleField|Collection|array|null  $subject
     * @param  LocaleField|Collection|array|null  $title
     * @param  LocaleField|Collection|array|null  $content
     * @param  LocaleField|Collection|array|null  $cta
     * @param  LocaleField|Collection|array|null  $cta_title
     * @param  string|null                        $template
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     *
     */
    public static function updateBySlug(
        string $slug,
        string|null $name = null,
        LocaleField|Collection|array|null $subject = null,
        LocaleField|Collection|array|null $title = null,
        LocaleField|Collection|array|null $content = null,
        LocaleField|Collection|array|null $cta = null,
        LocaleField|Collection|array|null $cta_title = null,
        string|null $template = null
    ): bool {
        return static::updateBy(['slug' => $slug], $name, $subject, $title, $content, $cta, $cta_title, $template);
    }

    /**
     *
     * Remove by id (instance mode)
     *
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     *
     */
    public function remove(): bool
    {
        return static::removeBy(['_id' => $this->_id]);
    }

    /**
     *
     * Remove by id (static)
     *
     * @param  ObjectId|string  $id
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     *
     */
    public static function removeById(ObjectId|string $id): bool
    {
        $instance = new static();
        $id = $instance->ensureObjectId($id);

        return static::removeBy(['_id' => $id]);
    }

    /**
     *
     * Remove by slug
     *
     * @param  string  $slug
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     *
     */
    public static function removeBySlug(string $slug): bool
    {
        return static::removeBy(['slug' => $slug]);
    }

    /**
     *
     * Update by the given query
     *
     * @param  array                              $query
     * @param  string|null                        $name
     * @param  LocaleField|Collection|array|null  $subject
     * @param  LocaleField|Collection|array|null  $title
     * @param  LocaleField|Collection|array|null  $content
     * @param  LocaleField|Collection|array|null  $cta
     * @param  LocaleField|Collection|array|null  $cta_title
     * @param  string|null                        $template
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     *
     */
    private static function updateBy(
        array $query,
        string|null $name = null,
        LocaleField|Collection|array|null $subject = null,
        LocaleField|Collection|array|null $title = null,
        LocaleField|Collection|array|null $content = null,
        LocaleField|Collection|array|null $cta = null,
        LocaleField|Collection|array|null $cta_title = null,
        string|null $template = null
    ): bool {
        if (ACL::hasPermission(User::$currentUser, ACL::write('emails'))) {
            $instance = new static();
            $set = ['last_modified' => time()];

            if ($name !== null) {
                $set['name'] = $name;
            }

            if ($subject !== null) {
                $set['subject'] = $subject;
            }

            if ($title !== null) {
                $set['title'] = $title;
            }

            if ($content !== null) {
                $set['content'] = $content;
            }

            if ($cta !== null) {
                $set['cta'] = $cta;
            }

            if ($cta_title !== null) {
                $set['cta_title'] = $cta_title;
            }

            if ($template !== null && $template !== '') {
                $set['template'] = $template;
            }

            $instance->updateOne($query, [
                '$set' => $set
            ]);

            return true;
        }

        return false;
    }

    /**
     *
     * Remove by the given query
     *
     * @param  array  $query
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     *
     */
    private static function removeBy(array $query): bool
    {
        if (ACL::hasPermission(User::$currentUser, ACL::write('emails'))) {
            $instance = new static();

            $instance->deleteOne($query);
            return true;
        }

        return false;
    }
}