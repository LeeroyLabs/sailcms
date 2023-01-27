<?php

namespace SailCMS\Types;

use SailCMS\Collection;
use SailCMS\Contracts\Castable;

class SocialMeta implements Castable
{
    public string $handle;
    public string $title;
    public string $description;
    public string $image;

    public array $customMeta;

    private array $defaultProperty = ['handle', 'title', 'description', 'image'];

    public function __construct(object $object = null)
    {
        // Default values to avoid initialization errors
        $this->handle = "";
        $this->title = "";
        $this->description = "";
        $this->image = "";

        if (!$object) {
            return;
        }

        if ($object instanceof Collection) {
            $object = $object->unwrap();
        }

        $customMeta = [];
        foreach ($object as $key => $value) {
            if (in_array($key, $this->defaultProperty)) {
                $this->{$key} = (string)$value;
            } else {
                $customMeta[$key] = $value ?? '';
            }
        }

        $this->customMeta = $customMeta;
    }

    public function __get(string $name): mixed
    {
        if (in_array($name, $this->defaultProperty)) {
            return $this->{$name} ?? null;
        }

        return $this->customMeta[$name] ?? "";
    }

    public function __set(string $name, mixed $value): void
    {
        if (in_array($name, $this->defaultProperty)) {
            $this->{$name} = $value;
            return;
        }

        $this->customMeta[$name] = $value;
    }

    public function castFrom(): array
    {
        return [
            'handle' => $this->handle,
            'title' => $this->title,
            'description' => $this->description,
            'image' => $this->image,
            ...$this->customMeta
        ];
    }

    public function castTo(mixed $value): SocialMeta
    {
        if (is_array($value)) {
            $value = (object)$value;
        }

        return new self($value);
    }
}