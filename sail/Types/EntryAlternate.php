<?php

namespace SailCMS\Types;

use SailCMS\Errors\EntryException;
use SailCMS\Locale;
use SailCMS\Models\Entry;

class EntryAlternate
{
    /**
     *
     * @param string|null $locale
     * @param string $entry_id
     * @throws EntryException
     */
    public function __construct(
        public ?string $locale = null,
        public string  $entry_id = ''
    )
    {
        $locales = Locale::getAvailableLocales();

        if (isset($locale) && !$locales->contains($locale)) {
            $errorMsg = sprintf(Entry::INVALID_ALTERNATE_LOCALE[0], $locale);
            throw new EntryException($errorMsg, Entry::INVALID_ALTERNATE_LOCALE[1]);
        }
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
     * @param mixed $value
     * @return EntryAlternate
     * @throws EntryException
     *
     */
    public function castTo(mixed $value): self
    {
        return new self($value->locale, $value->entry_id);
    }
}