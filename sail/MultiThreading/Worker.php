<?php

namespace SailCMS\MultiThreading;

abstract class Worker
{
    public function initialize(mixed $data): void
    {
        // Implement me
    }

    public function onSuccess(): void
    {
        // Implement me
    }

    public function onFailure(): void
    {
        // Implement me
    }

    abstract public function execute(): bool;
}