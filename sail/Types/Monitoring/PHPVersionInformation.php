<?php

namespace SailCMS\Types\Monitoring;

use SailCMS\Contracts\Castable;
use stdClass;
use Stringable;

readonly class PHPVersionInformation implements Stringable, Castable
{
    public function __construct(public string $installed, public string $current, public bool $latest, public bool $secure, public bool $supported) { }

    /**
     * @throws \JsonException
     */
    public function __toString(): string
    {
        return json_encode([
            'installed' => $this->installed,
            'current' => $this->current,
            'latest' => $this->latest,
            'secure' => $this->secure,
            'supported' => $this->supported
        ], JSON_THROW_ON_ERROR);
    }

    /**
     *
     * @return stdClass
     *
     */
    public function castFrom(): stdClass
    {
        return (object)[
            'installed' => $this->installed,
            'current' => $this->current,
            'latest' => $this->latest,
            'secure' => $this->secure,
            'supported' => $this->supported
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
        return new self($value->installed, $value->current, $value->latest, $value->secure, $value->supported);
    }
}