<?php

namespace SailCMS\Types;

use SailCMS\Collection;
use SailCMS\Contracts\Castable;
use SailCMS\Debug;
use SailCMS\Errors\NavigationException;

class NavigationStructure implements Castable
{
    /**
     * @var NavigationElement[] $structure
     */
    private array $structure;

    public const HANDLE_ARRAY_CASTING = true; // This is required for Breeze to handle this castable as "array handling"

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
            if (is_object($element) && get_class($element) !== NavigationElement::class) {
                throw new NavigationException(
                    "Cannot add anything else than 'NavigationElement' objects in a navigation",
                    0400
                );
            }
        }

        // Process everything to be navigation elements
        foreach ($this->structure as $num => $element) {
            if (is_array($element)) {
                $this->structure[$num] = new NavigationElement(...$element);
            } else {
                $this->structure[$num] = $element;
            }
        }
    }

    /**
     *
     * Get the actual structure
     *
     * @return array|NavigationElement[]
     *
     */
    public function get(): array
    {
        return $this->structure;
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
        $list = [];

        foreach ($value as $item) {
            $el = (array)$item;
            $el['children'] = (array)$el['children'];
            $list[] = $el;
        }

        Debug::ray($list);

        return new self((array)$value->structure);
    }
}