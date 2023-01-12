<?php

namespace SailCMS\Contracts;

interface Castable
{
    public function castFrom(): mixed;

    public function castTo(mixed $value): mixed;
}