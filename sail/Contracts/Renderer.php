<?php

namespace SailCMS\Contracts;

interface Renderer
{
    public function identifier(): string;

    public function contentType(): string;

    public function useTwig(): bool;

    public function render(string $template, object $data): string;
}