<?php

namespace SailCMS\Types;

class LoginResult
{
    public function __construct(public readonly string $user_id, public readonly string $message) { }
}