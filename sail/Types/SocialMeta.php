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

    /**
     *
     * Class to handle Social Meta
     *
     * @param  string       $handle
     * @param  object|null  $content
     *
     */
    public function __construct(string $handle = "", object $content = null)
    {
        $this->handle = $handle;

        // Default values to avoid initialization errors
        $this->title = "";
        $this->description = "";
        $this->image = "";

        if (!$content) {
            return;
        }

        if ($content instanceof Collection) {
            $content = $content->unwrap();
        }

        $customMeta = [];
        foreach ($content as $key => $value) {
            if ($key === "handle") {
                continue;
            }

            if (in_array($key, $this->defaultProperty, true)) {
                $this->{$key} = (string)$value;
            } else {
                $customMeta[$key] = $value ?? '';
            }
        }

        $this->customMeta = $customMeta;
    }

    /**
     *
     * Magic getter
     *
     * @param  string  $name
     * @return mixed
     *
     */
    public function __get(string $name): mixed
    {
        if (in_array($name, $this->defaultProperty, true)) {
            return $this->{$name} ?? null;
        }

        return $this->customMeta[$name] ?? "";
    }

    /**
     *
     * Magic setter
     *
     * @param  string  $name
     * @param  mixed   $value
     * @return void
     *
     */
    public function __set(string $name, mixed $value): void
    {
        if (in_array($name, $this->defaultProperty, true)) {
            $this->{$name} = $value;
            return;
        }

        $this->customMeta[$name] = $value;
    }

    /**
     *
     * Cast from, when saving
     *
     * @return array
     *
     */
    public function castFrom(): array
    {
        return [
            'handle' => $this->handle,
            'content' => [
                'title' => $this->title,
                'description' => $this->description,
                'image' => $this->image,
                ...$this->customMeta ?? []
            ]
        ];
    }

    /**
     *
     * Cast to, when fetching
     *
     * @param  mixed  $value
     * @return SocialMeta
     *
     */
    public function castTo(mixed $value): SocialMeta
    {
        if (is_array($value)) {
            $value = (object)$value;
        }
        return new self($value->handle, $value->content ?? null);
    }
}