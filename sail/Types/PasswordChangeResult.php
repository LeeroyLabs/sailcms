<?php

namespace SailCMS\Types;

class PasswordChangeResult
{
    public function __construct(
        public readonly bool $password_check = false,
        public readonly bool $code_check = false
    ) {
    }
}