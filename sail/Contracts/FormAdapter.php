<?php

namespace SailCMS\Contracts;

use SailCMS\Models\Form;
use SailCMS\Types\FormProcessingResult;
use stdClass;

interface FormAdapter
{
    public function receive(Form $form, stdClass $post): FormProcessingResult;
}