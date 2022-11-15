<?php

namespace SailCMS\Models\Entry;

use SailCMS\Collection;
use SailCMS\Types\Fields\InputTextField;
use SailCMS\Types\LocaleField;

class TextField extends Field
{
    protected function defineLabels(): void
    {
        $this->labels = new LocaleField([
            'en' => 'Text',
            'fr' => 'Texte'
        ]);
    }

    protected function defineSchema(): void
    {
        $this->schema = new Collection([
            InputTextField::class
        ]);
    }

    protected function validate(Collection $content): ?Collection
    {
        // Nothing to implement
        return null;
    }
}