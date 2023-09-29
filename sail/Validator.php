<?php

namespace SailCMS;

use Exception;
use MongoDB\BSON\ObjectId;
use Respect\Validation\Rules\CreditCard;
use Respect\Validation\Validator as v;
use SailCMS\Errors\EntryException;
use SailCMS\Models\EntryField;
use const SailCMS\Validator\COUNTRY_LIST;

class Validator
{
    public const VALIDATION_SEPARATOR = "|";
    public const METHOD_DOES_NOT_EXIST = "6500: Validation method '%s' does not exist.";
    public const VALIDATION_FAILED = "6501: Validation of '%s' type failed.";

    /**
     *
     * Return a collection of validation failed
     *  -> if repeatable each failure index represent content index
     *
     * @param  mixed       $content
     * @param  EntryField  $entryField
     * @param  bool        $silently
     * @return Collection
     * @throws EntryException
     *
     */
    public static function validateContentWithEntryField(mixed $content, EntryField $entryField, bool $silently = false): Collection
    {
        $failedValidations = Collection::init();

        // No need to validate if the field does have validation value
        if (!$entryField->validation) {
            return $failedValidations;
        }

        if ($content instanceof Collection) {
            $content = $content->unwrap();
        }

        $validations = explode(self::VALIDATION_SEPARATOR, $entryField->validation);

        if ($entryField->repeatable || is_array($content)) {
            foreach ($content as $contentKey => $value) {
                $failedValidation = Collection::init();
                foreach ($validations as $validation) {
                    if (!self::validateContent($value, $entryField, $validation, $silently)) {
                        $failedValidation->push(sprintf(self::VALIDATION_FAILED, $validation));
                    }
                }
                if ($failedValidation->length > 0) {
                    $failedValidations->pushKeyValue($contentKey, $failedValidation);
                }
            }
        } else {
            foreach ($validations as $validation) {
                if (!self::validateContent($content, $entryField, $validation, $silently)) {
                    $failedValidations->push(sprintf(self::VALIDATION_FAILED, $validation));
                }
            }
        }

        // For multiple validation, empty the errors if one validation passed
        if ($failedValidations->length < count($validations)) {
            return Collection::init();
        }

        return $failedValidations;
    }

    /**
     *
     * Validate a content with a validation string
     *
     * @param  mixed        $content
     * @param  EntryField   $entryField
     * @param  string|null  $validation
     * @param  bool         $silently
     * @return bool
     * @throws EntryException
     *
     */
    public static function validateContent(mixed $content, EntryField $entryField, string $validation = null, bool $silently = false): bool
    {
        if (!$validation) {
            $validation = $entryField->validation;
        }

        if (str_contains(self::VALIDATION_SEPARATOR, $validation) || !method_exists(self::class, $validation) || !$validation) {
            if ($silently) {
                Log::error(self::METHOD_DOES_NOT_EXIST, ['content' => $content, 'field' => $entryField, 'validation' => $validation]);
            } else {
                throw new EntryException(self::METHOD_DOES_NOT_EXIST);
            }
        }

        switch ($validation) {
            case 'domain':
                $tld = $entryField->config?->tld ?? true;
                $args = [$content, $tld];
                break;
            case 'alpha':
            case 'alphanum':
                $extraChars = $entryField->config?->extraChars ?? [];
                if (!is_array($extraChars)) {
                    $extraChars = (array)$extraChars;
                }
                $args = [$content, $extraChars];
                break;
            case 'min':
                $min = $entryField->config?->min ?? -INF;
                $args = [$content, $min];
                break;
            case 'max':
                $max = $entryField->config?->max ?? INF;
                $args = [$content, $max];
                break;
            case 'between':
                $min = $entryField->config?->min ?? -INF;
                $max = $entryField->config?->max ?? INF;
                $args = [$content, $min, $max];
                break;
            case 'postal':
            case 'phone':
                $country = $entryField->config?->country ?? 'all';

                if (!is_string($country)) {
                    $country = (array)$country;
                }

                $args = [$country, $content];
                break;
            case 'date':
                $format = $entryField->config?->format ?? 'Y-m-d';
                $args = [$format, $content];
                break;
            case 'time':
                $format = $entryField->config?->format ?? 'H:i';
                $args = [$format, $content];
                break;
            case 'datetime':
                $format = $entryField->config?->format ?? 'Y-m-d H:i';
                $args = [$format, $content];
                break;
            case 'creditcard':
                $cardBrand = $entryField->config?->cardBrand ?? CreditCard::ANY;
                $args = [$content, $cardBrand];
                break;
            case 'uuid':
                $version = $entryField->config?->version ?? 4;
                $args = [$content, $version];
                break;
            default:
                $args = [$content];
                break;
        }

        try {
            return call_user_func([static::class, $validation], ...$args);
        }catch (Exception $e) {
            Log::warning(sprintf(self::METHOD_DOES_NOT_EXIST, $validation), []);
            return true;
        }
    }

    /**
     *
     * Validate that given value is not empty
     * supports: string, numeric and array values
     *
     * @param  mixed  $value
     * @return bool
     *
     */
    public static function required(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            return v::stringType()->notEmpty()->validate($value);
        }

        if (is_numeric($value)) {
            return v::numericVal()->notEmpty()->validate($value);
        }

        if (is_array($value)) {
            return v::arrayType()->notEmpty()->validate($value);
        }

        return true;
    }

    /**
     *
     * Check if value is boolean valid
     * (True/False in text, 1/0, true/false boolean, on/off string)
     *
     * @param  mixed  $value
     * @return bool
     *
     */
    public static function boolean(mixed $value): bool
    {
        return v::boolVal()->validate($value);
    }

    /**
     *
     * Validate Email
     *
     * @param  string  $email
     * @return bool
     *
     */
    public static function email(string $email): bool
    {
        return v::email()->validate($email);
    }

    /**
     *
     * Validate URL
     *
     * @param  string  $url
     * @return bool
     *
     */
    public static function url(string $url): bool
    {
        return v::url()->validate($url);
    }

    /**
     *
     * Validate a domain
     *
     * @param  string  $domain
     * @param  bool    $tld
     * @return bool
     *
     */
    public static function domain(string $domain, bool $tld = true): bool
    {
        return v::domain($tld)->validate($domain);
    }

    /**
     *
     * Validate if string is a valid ip
     *
     * @param  string  $ip
     * @return bool
     *
     */
    public static function ip(string $ip): bool
    {
        return v::ip()->validate($ip);
    }

    /**
     *
     * Validate an integer value (accepts 1 or '1')
     *
     * @param  mixed  $int
     * @return bool
     *
     */
    public static function integer(mixed $int): bool
    {
        return v::intVal()->validate($int);
    }

    /**
     *
     * Validate float value (accepts 1.2 or '1.2')
     *
     * @param  mixed  $float
     * @return bool
     *
     */
    public static function float(mixed $float): bool
    {
        return v::floatVal()->validate($float);
    }

    /**
     *
     * Check if value is numeric
     *
     * @param  mixed  $number
     * @return bool
     *
     */
    public static function numeric(mixed $number): bool
    {
        return v::numericVal()->validate($number);
    }

    /**
     *
     * Check if string is alpha only (and allowed special chars)
     *
     * @param  string  $string
     * @param  array   $extraChars
     * @return bool
     *
     */
    public static function alpha(string $string, array $extraChars = []): bool
    {
        return v::alpha(...$extraChars)->validate($string);
    }

    /**
     *
     * Check if string is alphanumeric only (and allowed special chars)
     *
     * @param  string  $string
     * @param  array   $extraChars
     * @return bool
     *
     */
    public static function alphanum(string $string, array $extraChars = []): bool
    {
        return v::alnum(...$extraChars)->validate($string);
    }

    /**
     *
     * Check if number is at least the set minimum
     * or check if string has length of set minimum
     *
     * @param  int|float|string  $target
     * @param  int|float         $min
     * @return bool
     *
     */
    public static function min(int|float|string $target, int|float $min): bool
    {
        if (is_string($target)) {
            return v::stringVal()->length($min)->validate($target);
        }

        return v::min($min)->validate($target);
    }

    /**
     *
     * Check if number is at least the set maximum
     * or check if string has length maximum of set maximum
     *
     * @param  int|float|string  $target
     * @param  int|float         $max
     * @return bool
     *
     */
    public static function max(int|float|string $target, int|float $max): bool
    {
        if (is_string($target)) {
            return v::stringVal()->length(null, $max)->validate($target);
        }

        return v::max($max)->validate($target);
    }

    /**
     *
     * Check if number is between min and max
     * or check if string length is between set min and max
     *
     * @param  int|float|string  $target
     * @param  int|float         $min
     * @param  int|float         $max
     * @return bool
     *
     */
    public static function between(int|float|string $target, int|float $min, int|float $max): bool
    {
        if (is_string($target)) {
            return v::stringVal()->length($min, $max)->validate($target);
        }

        return v::numericVal()->between($min, $max)->validate($target);
    }

    /**
     *
     * Validate if given string is a valid mongodb id
     *
     * @param  string  $id
     * @return bool
     *
     */
    public static function id(string $id): bool
    {
        try {
            $_id = new ObjectId($id);
            return true;
        } catch (Exception) {
            return false;
        }
    }

    /**
     *
     * Validate that string is a valid Hex color
     *
     * @param  string  $color
     * @return bool
     *
     */
    public static function hexColor(string $color): bool
    {
        return v::hexRgbColor()->validate($color);
    }

    /**
     *
     * Validate that string is a valid directory path
     *
     * @param  string  $dir
     * @return bool
     *
     */
    public static function directory(string $dir): bool
    {
        return v::directory()->validate($dir);
    }

    /**
     *
     * Validate that string is a valid file path
     *
     * @param  string  $file
     * @return bool
     *
     */
    public static function file(string $file): bool
    {
        return v::file()->validate($file);
    }

    /**
     *
     * Validate postal code for canada
     *
     * @param  string  $postal
     * @return bool
     *
     */
    public static function postalCode(string $postal): bool
    {
        return v::postalCode('CA')->validate($postal);
    }

    /**
     *
     * Validate US zip code
     *
     * @param  string  $zip
     * @return bool
     *
     */
    public static function zip(string $zip): bool
    {
        return v::postalCode('US')->validate($zip);
    }

    /**
     *
     * Validate one, many or all countries against the postal code given
     *
     * @param  string|array  $country
     * @param  string        $value
     * @return bool
     *
     */
    public static function postal(string|array $country, string $value): bool
    {
        if (is_string($country) && $country !== 'all') {
            return v::postalCode($country)->validate($value);
        }

        if (is_array($country)) {
            $hasAcceptable = false;

            foreach ($country as $code) {
                $hasAcceptable = v::postalCode($code)->validate($value);

                if ($hasAcceptable) {
                    break;
                }
            }

            return $hasAcceptable;
        }

        return self::postal(COUNTRY_LIST, $value);
    }

    /**
     *
     * Validate country code
     *
     * @param  string  $code
     * @return bool
     *
     */
    public static function countryCode(string $code): bool
    {
        return v::countryCode()->validate($code);
    }

    /**
     *
     * Validate phone for given single, many or all countries
     *
     * @param  string|array  $country
     * @param  string        $value
     * @return bool
     *
     */
    public static function phone(string|array $country, string $value): bool
    {
        // This code is not supported in the current version of
        if (is_string($country) && $country !== 'all') {
            return v::phone($country)->validate($value);
        }

        if (is_array($country)) {
            $hasAcceptable = false;

            foreach ($country as $code) {
                $hasAcceptable = v::phone($code)->validate($value);

                if ($hasAcceptable) {
                    break;
                }
            }

            return $hasAcceptable;
        }

        return self::phone(COUNTRY_LIST, $value);
    }

    /**
     *
     * Validate date is in the accepted format
     *
     * @param  string  $format
     * @param  string  $date
     * @return bool
     *
     */
    public static function date(string $format, string $date): bool
    {
        return v::date($format)->validate($date);
    }

    /**
     *
     * Validate time is in the accepted format
     *
     * @param  string  $format
     * @param  string  $date
     * @return bool
     *
     */
    public static function time(string $format, string $date): bool
    {
        return v::time($format)->validate($date);
    }

    /**
     *
     * Validate datetime is in the accepted format
     *
     * @param  string  $format
     * @param  string  $date
     * @return bool
     *
     */
    public static function datetime(string $format, string $date): bool
    {
        return v::dateTime($format)->validate($date);
    }

    /**
     *
     * luhn validation on credit card
     *
     * @param  string  $number
     * @param  string  $cardBrand
     * @return bool
     *
     */
    public static function creditcard(string $number, string $cardBrand = CreditCard::ANY): bool
    {
        return v::creditCard($cardBrand)->validate($number);
    }

    /**
     *
     * Validate uuid for version 1,3,4 or 5
     *
     * @param  string  $string
     * @param  int     $version
     * @return bool
     *
     */
    public static function uuid(string $string, int $version = 4): bool
    {
        if ($version !== 1 && $version !== 3 && $version !== 4 && $version !== 5) {
            return false;
        }

        return v::uuid($version)->validate($string);
    }

    /**
     *
     * Strip html script tags from html
     *
     * @param string $html
     * @return bool
     *
     */
    public static function html(string $html): bool
    {
        if (preg_match('#<script(.*?)>(.*?)</script>#is', $html)) {
            return false;
        }

        return v::stringVal()->validate($html);
    }
}