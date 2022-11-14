<?php

namespace SailCMS\Database;

abstract class Migration
{
    public string $name = '';

    public function __construct()
    {
        $this->name = get_class($this);
    }

    abstract public function up(): void;

    abstract public function down(): void;
}