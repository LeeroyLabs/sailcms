<?php

namespace SailCMS\Types;

class MailPreviewData
{
    public function __construct(public readonly string $template = '', public readonly array $context = [])
    {
    }
}