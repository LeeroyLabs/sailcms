<?php

namespace SailCMS\Http\Input;

use JsonException;
use SailCMS\Http\Input;

class Post extends Input
{
    /**
     *
     * @throws JsonException
     *
     */
    public function __construct()
    {
        if (empty($_POST)) {
            $input = file_get_contents('php://input');

            if (!empty($input)) {
                $this->pairs = json_decode($input, true, 512, JSON_THROW_ON_ERROR);
            }

            return;
        }

        $this->pairs = $_POST;
    }
}