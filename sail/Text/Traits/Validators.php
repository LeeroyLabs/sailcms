<?php

namespace SailCMS\Text\Traits;

use \JsonException;
use SailCMS\Text;

trait Validators
{
    /**
     *
     * Is the string a valid email
     *
     * @return bool
     *
     */
    public function isEmail(): bool
    {
        return filter_var($this->internalString, FILTER_VALIDATE_EMAIL);
    }

    /**
     *
     * Is the string a valid domain
     *
     * @return bool
     *
     */
    public function isDomain(): bool
    {
        $regex = "([a-z0-9+!*(),;?&=\$_.-]+(\:[a-z0-9+!*(),;?&=\$_.-]+)?@)?";
        $regex .= "([a-z0-9-.]*)\.([a-z]{2,3})";

        if (preg_match("/^$regex$/i", $this->internalString)) {
            return true;
        }

        return false;
    }

    /**
     *
     * Is the string a valid URL
     *
     * @return bool
     *
     */
    public function isURL(): bool
    {
        $regex = "((https?|ftp)\:\/\/)?";
        $regex .= "([a-z0-9+!*(),;?&=\$_.-]+(\:[a-z0-9+!*(),;?&=\$_.-]+)?@)?";
        $regex .= "([a-z0-9-.]*)\.([a-z]{2,3})";
        $regex .= "(\:[0-9]{2,5})?";
        $regex .= "(\/([a-z0-9+\$_-]\.?)+)*\/?";
        $regex .= "(\?[a-z+&\$_.-][a-z0-9;:@&%=+\/\$_.-]*)?";
        $regex .= "(#[a-z_.-][a-z0-9+\$_.-]*)?";

        if (preg_match("/^$regex$/i", $this->internalString)) {
            return true;
        }

        return false;
    }

    /**
     *
     * Is the string a valid MAC Address
     *
     * @return bool
     *
     */
    public function isMacAddress(): bool
    {
        return filter_var($this->internalString, FILTER_VALIDATE_MAC);
    }

    /**
     *
     * Is the string a valid IP Address
     *
     * @return bool
     *
     */
    public function isIP(): bool
    {
        return filter_var($this->internalString, FILTER_VALIDATE_IP);
    }

    /**
     *
     * Is the string valid JSON
     *
     * @return bool
     *
     */
    public function isJSON(): bool
    {
        // Validate 2 basic first characters ([ or {)
        if (!str_starts_with($this->internalString, '[') && !str_starts_with($this->internalString, '{')) {
            return false;
        }

        try {
            json_decode($this->internalString, true, 512, JSON_THROW_ON_ERROR);
            return true;
        } catch (JsonException $e) {
            echo $e->getMessage();
            return false;
        }
    }

    public function isPostal(string $country = 'ca'): bool
    {
        $country_regex = [
            'uk' => '/\\A\\b[A-Z]{1,2}[0-9][A-Z0-9]? [0-9][ABD-HJLNP-UW-Z]{2}\\b\\z/i',
            'ca' => '/\\A\\b[ABCEGHJKLMNPRSTVXY][0-9][A-Z][ ]?[0-9][A-Z][0-9]\\b\\z/i',
            'it' => '/^[0-9]{5}$/i',
            'de' => '/^[0-9]{5}$/i',
            'be' => '/^[1-9]{1}[0-9]{3}$/i',
            'us' => '/\\A\\b[0-9]{5}(?:-[0-9]{4})?\\b\\z/i',
            'default' => '/\\A\\b[0-9]{5}(?:-[0-9]{4})?\\b\\z/i' // Same as US.
        ];

        if (isset($country_regex[$country])) {
            return preg_match($country_regex[$country], $this->internalString);
        }

        return preg_match($country_regex['default'], $this->internalString);
    }

    /**
     *
     * Alias for US use of isPostal
     *
     * @return bool
     *
     */
    public function isZip(): bool
    {
        return $this->isPostal('us');
    }

    // is

    /**
     *
     * Does the string start with given string?
     *
     * @param  string|Text  $string
     * @return bool
     *
     */
    public function startsWith(string|Text $string): bool
    {
        if (is_object($string)) {
            $string = $string->value();
        }

        return str_starts_with($this->internalString, $string);
    }

    /**
     *
     * Does the string end with given string?
     *
     * @param  string|Text  $string
     * @return bool
     *
     */
    public function endsWith(string|Text $string): bool
    {
        if (is_object($string)) {
            $string = $string->value();
        }

        return str_ends_with($this->internalString, $string);
    }

    /**
     *
     * Does the string contain given string?
     *
     * @param  string|Text  $string
     * @return bool
     *
     */
    public function contains(string|Text $string): bool
    {
        if (is_object($string)) {
            $string = $string->value();
        }

        return str_contains($this->internalString, $string);
    }
}