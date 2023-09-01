<?php

namespace SailCMS\Types\Monitoring;

use SailCMS\Contracts\Castable;
use stdClass;

readonly class RAMSample implements Castable
{
    public function __construct(public int $total = 0, public int $available = 0, public int $used = 0, public float $percent = 0)
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