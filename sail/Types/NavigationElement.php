<?php

namespace SailCMS\Types;

class NavigationElement
{
    public function __construct(
        public readonly string $label,
        public readonly string $url,
        public readonly bool $external = false,
        private array $children = []
    ) {
    }

    /**
     *
     * Add a NavigationElement at the end of the children array
     *
     * @param  NavigationElement  $child
     * @return void
     *
     */
    public function addChild(NavigationElement $child): void
    {
        $this->children[] = $child;
    }

    /**
     *
     * Insert an NavigationElement at given position
     *
     * @param  NavigationElement  $child
     * @param  int                $index
     * @return void
     *
     */
    public function insertChildAt(NavigationElement $child, int $index): void
    {
        $this->children = array_slice($this->children, $index, 0, $child);
    }

    /**
     *
     * Get the elements children objects
     *
     * @return array
     *
     */
    public function children(): array
    {
        return $this->children;
    }

    /**
     *
     * Simplify the structure for database/graphql
     *
     * @return array
     *
     */
    public function simplify(): array
    {
        $children = [];

        foreach ($this->children as $child) {
            $children[] = $child->simplify();
        }

        return [
            'label' => $this->label,
            'url' => $this->url,
            'external' => $this->external,
            'children' => $children
        ];
    }
}
