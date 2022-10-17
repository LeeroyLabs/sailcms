<?php

namespace SailCMS\Contracts;

use SailCMS\Collection;

interface AppSession
{
    public function set(string $key, mixed $value): void;

    public function get(string $key): mixed;

    public function remove(string $key): void;

    public function all(): Collection;

    public function clear(): void;

    public function getId(): string;

    public function type(): string;
}