<?php

namespace SailCMS\Attributes\Routing;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Secure
{
    public function __construct()
    {
    }
}