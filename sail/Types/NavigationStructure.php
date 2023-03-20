<?php

namespace SailCMS\Types;

use SailCMS\Collection;
use SailCMS\Contracts\Castable;
use SailCMS\Errors\NavigationException;

class NavigationStructure implements Castable
{
    /**
     * @var NavigationElement[] $structure
     */
    public readonly array $structure;

    /**
     *
     * Build the structure
     *
     * @param  array|Collection  $structure
     * @throws NavigationException
     *
     */
    public function __construct(array|Collection $structure = [])
    {
        if (!is_array($structure)) {
            $this->structure = $structure->unwrap();
        } else {
            $this->structure = $structure;
        }

        // Very every element in array to make sure we have NavigationElements
        foreach ($this->structure as $element) {
            if (!is_object($element) || get_class($element) !== NavigationElement::class) {
                throw new NavigationException(
                    "Cannot add anything else than 'NavigationElement' objects in a navigation",
                    0400
                );
            }
        }
    }

    /**
     *
     * Cast to simpler type
     *
     * @return array
     *
     */
    public function castFrom(): array
    {
        $out = [];

        foreach ($this->structure as $element) {
            $out[] = $element->simplify();
        }

        return $out;
    }

    /**
     *
     * Create instance of NavigationStructure
     *
     * @param  mixed  $value
     * @return $this
     * @throws NavigationException
     *
     */
    public function castTo(mixed $value): self
    {
        return new self($value);
    }
}