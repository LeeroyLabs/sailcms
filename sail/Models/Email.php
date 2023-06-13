<?php

namespace SailCMS\Models;

use MongoDB\BSON\ObjectId;
use SailCMS\ACL;
use SailCMS\Collection;
use SailCMS\Database\Model;
use SailCMS\Errors\ACLException;
use SailCMS\Errors\DatabaseException;
use SailCMS\Errors\EmailException;
use SailCMS\Errors\FileException;
use SailCMS\Errors\PermissionException;
use SailCMS\Mail;
use SailCMS\Text;
use SailCMS\Types\LocaleField;
use Twig\Error\LoaderError;

/**
 *
 * @property string      $name
 * @property string      $slug
 * @property LocaleField $subject
 * @property LocaleField $title
 * @property LocaleField $content
 * @property LocaleField $cta
 * @property LocaleField $cta_title
 * @property string      $template
 * @property int         $created_at
 * @property int         $last_modified
 * @property string      $site_id
 * @property bool        $is_preview
 * @property string      $created_by
 *
 */
class Email extends Model
{
    protected string $collection = 'emails';
    protected array $casting = [
        'subject' => LocaleField::class,
        'title' => LocaleField::class,
        'content' => LocaleField::class,
        'cta' => LocaleField::class,
        'cta_title' => LocaleField::class
    ];

    protected string $permissionGroup = 'emails';

    /**
     *
     * Get an email by slug
     *
     * @param  string  $slug
     * @param  string  $siteId
     * @return Email|null
     * @throws DatabaseException
     *
     */
    public static function getBySlug(string $slug, string $siteId = 'default'): ?Email
    {
        return self::query()->findOne(['slug' => $slug, 'site_id' => $siteId])->exec();
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
     * @param  string  $siteId
     * @return Collection
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function getList(string $siteId): Collection
    {
        $this->hasPermissions(true);
        return new Collection($this->find(['is_preview' => false, 'site_id' => $siteId])->exec());
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
     * @param  string                        $siteId
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws EmailException
     * @throws PermissionException
     *
     */
    public function create(
        string $name,
        LocaleField|Collection|array $subject,
        LocaleField|Collection|array $title,
        LocaleField|Collection|array $content,
        LocaleField|Collection|array $cta,
        LocaleField|Collection|array $cta_title,
        string $template,
        string $siteId = 'default'
    ): bool {
        $this->hasPermissions();

        $slug = Text::from($name)->deburr()->snake()->value();
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
            'last_modified' => time(),
            'site_id' => $siteId,
            'is_preview' => false,
            'created_by' => User::$currentUser->id
        ]);

        return true;
    }

    /**
     *
     * Create a preview email and return its slug
     *
     * @param  string                        $name
     * @param  LocaleField|Collection|array  $subject
     * @param  LocaleField|Collection|array  $title
     * @param  LocaleField|Collection|array  $content
     * @param  LocaleField|Collection|array  $cta
     * @param  LocaleField|Collection|array  $cta_title
     * @param  string                        $template
     * @param  string                        $siteId
     * @return string
     * @throws ACLException
     * @throws DatabaseException
     * @throws EmailException
     * @throws PermissionException
     *
     */
    public function createPreview(
        string $name,
        LocaleField|Collection|array $subject,
        LocaleField|Collection|array $title,
        LocaleField|Collection|array $content,
        LocaleField|Collection|array $cta,
        LocaleField|Collection|array $cta_title,
        string $template,
        string $siteId = 'default'
    ): string {
        $this->hasPermissions();

        $slug = Text::from($name)->deburr()->snake()->value();

        // Remove all previews from user
        $this->deleteMany(['created_by' => User::$currentUser->id]);

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
            'last_modified' => time(),
            'site_id' => $siteId,
            'is_preview' => true,
            'created_by' => User::$currentUser->id
        ]);

        return $slug;
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
     * @throws PermissionException
     *
     */
    public function update(
        string|null $name = null,
        LocaleField|Collection|array|null $subject = null,
        LocaleField|Collection|array|null $title = null,
        LocaleField|Collection|array|null $content = null,
        LocaleField|Collection|array|null $cta = null,
        LocaleField|Collection|array|null $cta_title = null,
        string|null $template = null,
    ): bool {
        $this->hasPermissions();
        return self::updateBy(['_id' => $this->_id], $name, $subject, $title, $content, $cta, $cta_title, $template);
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
     * @throws PermissionException
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
        $instance->hasPermissions();

        $id = $instance->ensureObjectId($id);
        return self::updateBy(['_id' => $id], $name, $subject, $title, $content, $cta, $cta_title, $template);
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
     * @param  string                             $siteId
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
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
        string|null $template = null,
        string $siteId = 'default'
    ): bool {
        $instance = new static();
        $instance->hasPermissions();
        return self::updateBy(['slug' => $slug, 'site_id' => $siteId], $name, $subject, $title, $content, $cta, $cta_title, $template);
    }

    /**
     *
     * Remove by id (instance mode)
     *
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public function remove(): bool
    {
        $this->hasPermissions();
        return self::removeBy(['_id' => $this->_id]);
    }

    /**
     *
     * Remove by id (static)
     *
     * @param  ObjectId|string  $id
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public static function removeById(ObjectId|string $id): bool
    {
        $instance = new static();
        $instance->hasPermissions();
        $id = $instance->ensureObjectId($id);

        return self::removeBy(['_id' => $id]);
    }

    /**
     *
     * Remove by slug
     *
     * @param  string  $slug
     * @param  string  $siteId
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public static function removeBySlug(string $slug, string $siteId = 'default'): bool
    {
        $instance = new static();
        $instance->hasPermissions();
        return self::removeBy(['slug' => $slug, 'site_id' => $siteId]);
    }

    /**
     *
     * Delete a list of emails
     *
     * @param  Collection|array  $ids
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public static function removeList(Collection|array $ids): bool
    {
        $instance = new static();
        $instance->hasPermissions();
        $dbIds = $instance->ensureObjectIds($ids);
        $count = $instance->deleteMany(['_id' => $dbIds]);
        return ($count > 0);
    }

    /**
     *
     * Send a test email
     *
     * @param  string  $email
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws EmailException
     * @throws PermissionException
     * @throws FileException
     * @throws LoaderError
     *
     */
    public static function sendTest(string $email): bool
    {
        $instance = new static();
        $instance->hasPermissions(true);

        $mail = new Mail();
        return $mail->to($email)->useEmail(
            'test',
            'en',
            [
                'email_title' => 'Congratulations',
                'email_content' => '
                    Congrats! Your configuration is valid and emails can be sent from SailCMS. All features that use
                    email will be able to proceed with emailing.
                '
            ]
        )->send();
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
     * @param  LocaleField|Collection|array|null  $ctaTitle
     * @param  string|null                        $template
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    private static function updateBy(
        array $query,
        string|null $name = null,
        LocaleField|Collection|array|null $subject = null,
        LocaleField|Collection|array|null $title = null,
        LocaleField|Collection|array|null $content = null,
        LocaleField|Collection|array|null $cta = null,
        LocaleField|Collection|array|null $ctaTitle = null,
        string|null $template = null
    ): bool {
        $instance = new static();
        $instance->hasPermissions();

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

        if ($ctaTitle !== null) {
            $set['cta_title'] = $ctaTitle;
        }

        if ($template !== null && $template !== '') {
            $set['template'] = $template;
        }

        $instance->updateOne($query, [
            '$set' => $set
        ]);

        return true;
    }

    /**
     *
     * Remove by the given query
     *
     * @param  array  $query
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    private static function removeBy(array $query): bool
    {
        $instance = new static();
        $instance->hasPermissions();
        $instance->deleteOne($query);
        return true;
    }
}