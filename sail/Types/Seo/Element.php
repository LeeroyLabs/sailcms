<?php

namespace SailCMS\Types\Seo;

class Element implements \Stringable
{
    public function __construct(private object $elementData, private string $key, string $type = 'title')
    {
    }

    public function tag(): string
    {
        return '';
    }

    public function meta(): string
    {
        return '';
    }

    public function value(): string
    {
        if (!empty($this->elementData->{$this->key})) {
            return $this->elementData->{$this->key};
        }

        return '';
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->elementData->{$this->key};
    }
}