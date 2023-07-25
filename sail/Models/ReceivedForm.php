<?php

namespace SailCMS\Model;

use SailCMS\Database\Model;
use stdClass;

/**
 *
 * @property string   $form_name
 * @property int      $received_date
 * @property string   $received_from
 * @property stdClass $form_data
 *
 */
class ReceivedForm extends Model
{
    protected string $collection = 'received_forms';
}