<?php

namespace SailCMS\Models\Entry;

use SailCMS\Collection;
use SailCMS\Types\Fields\InputTextField;

class TextField extends Field
{
    public function defaultSettings(): Collection
    {
        return new Collection([
            InputTextField::defaultSettings()
        ]);
    }

    protected function defineBaseConfigs(): void
    {
        $this->baseConfigs = new Collection([
            InputTextField::class
        ]);
    }

    protected function validate(Collection $content): ?Collection
    {
        // Nothing to implement
        return null;
    }
}