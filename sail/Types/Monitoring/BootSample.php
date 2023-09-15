<?php

namespace SailCMS\Types\Monitoring;

use SailCMS\Contracts\Castable;
use stdClass;

readonly class BootSample implements Castable
{
    public function __construct(public int $boot_time = 0, public string $uptime = '')
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
            'boot_time' => $this->boot_time,
            'uptime' => $this->uptime
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
        return new self($value->boot_time, $value->uptime);
    }
}