<?php

namespace SailCMS\Models;

use SailCMS\Database\BaseModel;

class Asset extends BaseModel
{
    public string $filename;
    public string $url;
    public float $size;
    public string $uploader_id;
    public int $created_at;

    public function fields(bool $fetchAllFields = false): array
    {
        // TODO: Implement fields() method.
    }
}