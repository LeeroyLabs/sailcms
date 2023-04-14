<?php

namespace SailCMS\Text\Traits;

use Ramsey\Uuid\Uuid;
use SailCMS\Collection;
use SailCMS\Text;

trait Utilities
{
    /**
     *
     * Generate a random string of required length
     *
     * @param  int   $length
     * @param  bool  $symbols
     * @return Utilities|Text
     *
     */
    public function random(int $length, bool $symbols = true): self
    {
        if ($symbols) {
            $seed = str_split('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()');
        } else {
            $seed = str_split('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789');
        }

        $rand = '';

        foreach (array_rand($seed, $length) as $key) {
            $rand .= $seed[$key];
        }

        $this->internalString = $rand;
        return $this;
    }

    /**
     *
     * Generate a UUID v4 or v5
     *
     * @param  int     $version
     * @param  string  $ns
     * @param  string  $name
     * @return Text|Utilities
     *
     */
    public function uuid(int $version = 4, string $ns = '', string $name = ''): self
    {
        $this->internalString = match ($version) {
            4 => Uuid::uuid4(),
            5 => Uuid::uuid5($ns, hash('sha1', $name))
        };

        return $this;
    }
}