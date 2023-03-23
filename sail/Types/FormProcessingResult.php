<?php

namespace SailCMS\Types;

class FormProcessingResult
{
    public function __construct(
        public readonly bool $success = false,
        public readonly string $message = 'OK'
    )
    {
    }
}