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
 *
 */
class EmailDeprecated extends Model
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
     * @return EmailDeprecated|null
     * @throws DatabaseException
     *
     */
    public static function getBySlug(string $slug): ?self
    {
        return self::query()->findOne(['slug' => $slug])->exec();
    }

    /**
     *
     * Get an email by id
     *
     * @param  ObjectId|string  $id
     * @return EmailDeprecated|null
     * @throws DatabaseException
     *
     */
    public function getById(ObjectId|string $id): ?self
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
     * @throws PermissionException
     *
     */
    public function getList(): Collection
    {
        $this->hasPermissions(true);
        return new Collection($this->find([])->exec());
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
        string $template
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
            'last_modified' => time()
        ]);

        return true;
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
        string|null $template = null
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
        string|null $template = null
    ): bool {
        $instance = new static();
        $instance->hasPermissions();
        return self::updateBy(['slug' => $slug], $name, $subject, $title, $content, $cta, $cta_title, $template);
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
     * @return bool
     * @throws ACLException
     * @throws DatabaseException
     * @throws PermissionException
     *
     */
    public static function removeBySlug(string $slug): bool
    {
        $instance = new static();
        $instance->hasPermissions();
        return self::removeBy(['slug' => $slug]);
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
            1,
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