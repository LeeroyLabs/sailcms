<?php

namespace SailCMS\Types;

class EntryAlternate
{
    /**
     *
     * @param  string|null  $locale
     * @param  string       $entry_id
     *
     */
    public function __construct(
        public ?string $locale = null,
        public string  $entry_id = '',
    )
    {
    }

    /**
     *
     * Cast to simple version from EntryParent
     *
     * @return array
     *
     */
    public function castFrom(): array
    {
        return [
            'handle' => $this->locale ?? '',
            'parent_id' => $this->entry_id ?? ''
        ];
    }

    /**
     *
     * Cast to EntryAlternate
     *
     * @param  mixed  $value
     * @return EntryAlternate
     *
     */
    public function castTo(mixed $value): self
    {
        return new self($value->locale, $value->entry_id);
    }
}