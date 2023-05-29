<?php

namespace SailCMS;

use SailCMS\Text\Traits\Crypto;
use SailCMS\Text\Traits\Transforms;
use SailCMS\Text\Traits\Utilities;
use SailCMS\Text\Traits\Validators;
use Stringable;

/**
 *
 * @property int $length
 *
 */
class Text implements Stringable
{
    use Transforms;
    use Utilities;
    use Validators;
    use Crypto;

    protected string $internalString = '';

    public function __construct(string $string)
    {
        $this->internalString = $string;
    }

    /**
     *
     * Init with empty string
     *
     * @return self
     *
     */
    public static function init(): self
    {
        return new Text('');
    }

    /**
     *
     * Shorthand initializer
     *
     * @param  string|bool|int|float  $string  $string
     * @return self
     */
    public static function from(string|bool|int|float $string = ''): self
    {
        if (is_bool($string)) {
            $string = ($string) ? 'true' : 'false';
        } elseif (is_numeric($string)) {
            $string = (string)$string;
        }

        return new self($string);
    }

    /**
     *
     * Getters
     *
     * @param  string  $name
     * @return int
     *
     */
    public function __get(string $name)
    {
        return match ($name) {
            'length' => strlen($this->internalString)
        };
    }

    /**
     *
     * Get native value
     *
     * @return string
     *
     */
    public function value(): string
    {
        return $this->internalString;
    }

    /**
     *
     * Support any case that this class is used in a string context
     *
     * @return string
     *
     */
    public function __toString(): string
    {
        return $this->internalString;
    }
}