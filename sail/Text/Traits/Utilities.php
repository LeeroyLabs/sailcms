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

    /**
     *
     * Add substring to the string (with or without glue character)
     *
     * @param  string|Text  $string
     * @param  string       $glue
     * @return Text|Utilities
     *
     */
    public function concat(string|Text $string, string $glue = ''): self
    {
        if (is_object($string)) {
            $string = $string->value();
        }

        $this->internalString .= $glue . $string;
        return $this;
    }

    /**
     *
     * Alias of concat
     *
     * @param  string|Text  $string
     * @param  string       $glue
     * @return Text|Utilities
     *
     */
    public function merge(string|Text $string, string $glue = ''): self
    {
        return $this->concat($string, $glue);
    }

    /**
     *
     * Alias of concat
     *
     * @param  string|Text  $string
     * @param  string       $glue
     * @return Text|Utilities
     *
     */
    public function with(string|Text $string, string $glue = ''): self
    {
        return $this->concat($string, $glue);
    }

    /**
     *
     * Censor string if it has the given blacklisted words
     *
     * @param  array|Collection  $blacklist
     * @return Text|Utilities
     *
     */
    public function censor(array|Collection $blacklist): self
    {
        $blacklistWords = [];

        foreach ($blacklist as $word) {
            $length = strlen($word);
            $blacklistWords[$word] = str_pad('', $length, '*');
        }

        $toReplace = array_keys($blacklistWords);
        $replaceBy = array_values($blacklistWords);

        $this->internalString = str_ireplace($toReplace, $replaceBy, $this->internalString);
        return $this;
    }

    /**
     *
     * Switch new lines to BR tags
     *
     * @return Text|Utilities
     *
     */
    public function br(): self
    {
        $this->internalString = nl2br($this->internalString);
        return $this;
    }

    /**
     *
     * switch BR tags to new lines
     *
     * @return Text|Utilities
     *
     */
    public function nl(): self
    {
        $this->internalString = str_ireplace(['<br>', '<br >', '<br/>', '<br />'], PHP_EOL, $this->internalString);
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
            5 => Uuid::uuid5($ns, $name)
        };
    }
}