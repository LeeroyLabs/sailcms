<?php

namespace SailCMS\Http\Input;

use SailCMS\Http\Input;

class Post extends Input
{
    public function __construct()
    {
        $this->pairs = $_POST;
    }
}