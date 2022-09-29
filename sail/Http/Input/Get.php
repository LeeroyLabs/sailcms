<?php

namespace SailCMS\Http\Input;

use SailCMS\Http\Input;

class Get extends Input
{
    public function __construct()
    {
        $this->pairs = $_GET;
    }
}