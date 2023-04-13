<?php

namespace SailCMS\Text\Traits;

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
     * Multibyte enabled substr
     *
     * @param  int       $offset
     * @param  int|null  $length
     * @return Text|Utilities
     *
     */
    public function substr(int $offset, int $length = null): self
    {
        $this->internalString = mb_substr($this->internalString, $offset, $length);
        return $this;
    }

    /**
     *
     * Alias for substr
     *
     * @param  int       $offset
     * @param  int|null  $length
     * @return Utilities|Text
     *
     */
    public function substring(int $offset, int $length = null): self
    {
        return $this->substr($offset, $length);
    }
}