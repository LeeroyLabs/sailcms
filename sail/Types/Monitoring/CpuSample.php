<?php

namespace SailCMS\Types\Monitoring;

use SailCMS\Contracts\Castable;
use stdClass;

readonly class CpuSample implements Castable
{
    public function __construct(public float $percent = 0, public int $cores = 0)
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
            'percent' => $this->percent,
            'cores' => $this->cores
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
        return new self($value->percent, $value->cores);
    }
}