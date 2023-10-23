<?php

namespace SailCMS\Templating\Extensions;

use SailCMS\Models\EntryType;
use Throwable;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use SailCMS\Entry as EntryApi;

class Entry extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('entry', [$this, 'entry']),
        ];
    }

    public function getFilters(): array
    {
        return [];
    }


    public function entry(string $entryTypeHandle = EntryType::DEFAULT_HANDLE): mixed
    {

        try {
            return EntryApi::from($entryTypeHandle);
        } catch (Throwable $throw) {
            // Fail silently but log error here
            return [];
        }
    }
}