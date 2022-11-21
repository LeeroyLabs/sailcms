<?php

namespace SailCMS;

use PhpInflector\Inflector;

class Text
{
    /**
     *
     * Pluralize a string
     *
     * @param  string  $string
     * @return string
     *
     */
    public static function pluralize(string $string): string
    {
        return Inflector::pluralize($string);
    }

    /**
     *
     * singularize a string
     *
     * @param  string  $string
     * @return string
     *
     */
    public static function singularize(string $string): string
    {
        return Inflector::singularize($string);
    }

    /**
     *
     * Remove all accents from string
     *
     * @param  string  $string
     * @param  string  $separator
     * @return string
     *
     */
    public static function deburr(string $string, string $separator = '-'): string
    {
        return Inflector::parameterize($string, $separator);
    }

    /**
     *
     * Change string to kebab case
     *
     * @param  string  $string
     * @return string
     *
     */
    public static function kebabCase(string $string): string
    {
        return Inflector::parameterize($string);
    }

    /**
     *
     * Change string to slug
     *
     * @param  string  $string
     * @param  string  $locale
     * @return string
     *
     */
    public static function slugify(string $string, string $locale = 'en'): string
    {
        if ($locale === 'fr') {
            return Inflector::parameterize(str_replace('&', 'et', $string));
        }

        return Inflector::parameterize(str_replace('&', 'and', $string));
    }

    /**
     *
     * camelCase the string
     *
     * @param  string  $word
     * @return string
     *
     */
    public static function camelCase(string $word): string
    {
        return Inflector::camelize($word);
    }

    /**
     *
     * SnakeCase given string
     *
     * @param  string  $word
     * @return string
     *
     */
    public static function snakeCase(string $word): string
    {
        return Inflector::underscore($word);
    }
}