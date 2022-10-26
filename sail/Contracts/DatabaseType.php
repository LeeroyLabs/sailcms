<?php

namespace SailCMS\Contracts;

interface DatabaseType
{
    public function toDBObject(): \stdClass|array;
}