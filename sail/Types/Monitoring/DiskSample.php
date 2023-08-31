<?php

namespace SailCMS\Types\Monitoring;

use SailCMS\Contracts\Castable;
use stdClass;

readonly class DiskSample implements Castable
{
    public function __construct(public int $total, public int $available, public int $used, public float $percent)
    {
    }

    /**
     *
     * @return stdClass
     *
     */
    public function castFrom(): stdClass
    {
        return (object)[
            'total' => $this->total,
            'available' => $this->available,
            'used' => $this->used,
            'percent' => $this->percent
        ];
    }

    /**
     *
     * @param  mixed  $value
     * @return $this
     *
     */
    public function castTo(mixed $value): self
    {
        return new self($value->total, $value->available, $value->used, $value->percent);
    }
}