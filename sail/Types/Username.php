<?php

namespace SailCMS\Types;

use SailCMS\Contracts\Castable;

class Username implements Castable
{
    public string $full;

    public function __construct(public string $first = '', public string $last = '')
    {
        $this->full = $first . ' ' . $last;
    }

    /**
     *
     * Return an instance with a regular object
     *
     * @param  object  $name
     * @return Username
     *
     */
    public static function initWith(object $name): Username
    {
        return new self($name->first, $name->last, $name->first . ' ' . $name->last);
    }

    /**
     *
     * Cast to simpler format from Username
     *
     * @return array
     *
     */
    public function castFrom(): array
    {
        return [
            'first' => $this->first,
            'last' => $this->last,
            'full' => $this->full
        ];
    }

    /**
     *
     * Cast to Username
     *
     * @param  mixed  $value
     * @return Username
     *
     */
    public function castTo(mixed $value): Username
    {
        if (is_array($value)) {
            $value = (object)$value;
        }

        return new self($value->first ?? '', $value->last ?? '');
    }
}