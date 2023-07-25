<?php

namespace SailCMS\Models;

use SailCMS\Collection;
use SailCMS\Database\Model;
use SailCMS\Types\FormElement;

/**
 *
 * @property string                   $title
 * @property string                   $name
 * @property string                   $adapter
 * @property Collection|FormElement[] $elements
 * @property string                   $created_by
 * @property string                   $modified_by
 * @property int                      $created_at
 * @property int                      $modified_at
 * @property bool                     $use_captcha
 * @property bool                     $notify
 * @property Collection|string[]      $notify_targets
 *
 */
class Form extends Model
{
    protected string $collection = 'forms';
    protected string $permissionGroup = 'form';
    protected array $casting = [
        'elements' => [Collection::class, FormElement::class],
        'notify_targets' => Collection::class
    ];
}