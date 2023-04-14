<?php

namespace SailCMS\Text\Traits;

use PhpInflector\Inflector;
use SailCMS\Collection;
use SailCMS\Sail;
use SailCMS\Text;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\String\UnicodeString;

trait Transforms
{
    private const deburredLetters = [
        // Latin-1 Supplement block.
        '\xc0' => 'A',
        '\xc1' => 'A',
        '\xc2' => 'A',
        '\xc3' => 'A',
        '\xc4' => 'A',
        '\xc5' => 'A',
        '\xe0' => 'a',
        '\xe1' => 'a',
        '\xe2' => 'a',
        '\xe3' => 'a',
        '\xe4' => 'a',
        '\xe5' => 'a',
        '\xc7' => 'C',
        '\xe7' => 'c',
        '\xd0' => 'D',
        '\xf0' => 'd',
        '\xc8' => 'E',
        '\xc9' => 'E',
        '\xca' => 'E',
        '\xcb' => 'E',
        '\xe8' => 'e',
        '\xe9' => 'e',
        '\xea' => 'e',
        '\xeb' => 'e',
        '\xcc' => 'I',
        '\xcd' => 'I',
        '\xce' => 'I',
        '\xcf' => 'I',
        '\xec' => 'i',
        '\xed' => 'i',
        '\xee' => 'i',
        '\xef' => 'i',
        '\xd1' => 'N',
        '\xf1' => 'n',
        '\xd2' => 'O',
        '\xd3' => 'O',
        '\xd4' => 'O',
        '\xd5' => 'O',
        '\xd6' => 'O',
        '\xd8' => 'O',
        '\xf2' => 'o',
        '\xf3' => 'o',
        '\xf4' => 'o',
        '\xf5' => 'o',
        '\xf6' => 'o',
        '\xf8' => 'o',
        '\xd9' => 'U',
        '\xda' => 'U',
        '\xdb' => 'U',
        '\xdc' => 'U',
        '\xf9' => 'u',
        '\xfa' => 'u',
        '\xfb' => 'u',
        '\xfc' => 'u',
        '\xdd' => 'Y',
        '\xfd' => 'y',
        '\xff' => 'y',
        '\xc6' => 'Ae',
        '\xe6' => 'ae',
        '\xde' => 'Th',
        '\xfe' => 'th',
        '\xdf' => 'ss',
        // Latin Extended-A block.
        '\x{0100}' => 'A',
        '\x{0102}' => 'A',
        '\x{0104}' => 'A',
        '\x{0101}' => 'a',
        '\x{0103}' => 'a',
        '\x{0105}' => 'a',
        '\x{0106}' => 'C',
        '\x{0108}' => 'C',
        '\x{010a}' => 'C',
        '\x{010c}' => 'C',
        '\x{0107}' => 'c',
        '\x{0109}' => 'c',
        '\x{010b}' => 'c',
        '\x{010d}' => 'c',
        '\x{010e}' => 'D',
        '\x{0110}' => 'D',
        '\x{010f}' => 'd',
        '\x{0111}' => 'd',
        '\x{0112}' => 'E',
        '\x{0114}' => 'E',
        '\x{0116}' => 'E',
        '\x{0118}' => 'E',
        '\x{011a}' => 'E',
        '\x{0113}' => 'e',
        '\x{0115}' => 'e',
        '\x{0117}' => 'e',
        '\x{0119}' => 'e',
        '\x{011b}' => 'e',
        '\x{011c}' => 'G',
        '\x{011e}' => 'G',
        '\x{0120}' => 'G',
        '\x{0122}' => 'G',
        '\x{011d}' => 'g',
        '\x{011f}' => 'g',
        '\x{0121}' => 'g',
        '\x{0123}' => 'g',
        '\x{0124}' => 'H',
        '\x{0126}' => 'H',
        '\x{0125}' => 'h',
        '\x{0127}' => 'h',
        '\x{0128}' => 'I',
        '\x{012a}' => 'I',
        '\x{012c}' => 'I',
        '\x{012e}' => 'I',
        '\x{0130}' => 'I',
        '\x{0129}' => 'i',
        '\x{012b}' => 'i',
        '\x{012d}' => 'i',
        '\x{012f}' => 'i',
        '\x{0131}' => 'i',
        '\x{0134}' => 'J',
        '\x{0135}' => 'j',
        '\x{0136}' => 'K',
        '\x{0137}' => 'k',
        '\x{0138}' => 'k',
        '\x{0139}' => 'L',
        '\x{013b}' => 'L',
        '\x{013d}' => 'L',
        '\x{013f}' => 'L',
        '\x{0141}' => 'L',
        '\x{013a}' => 'l',
        '\x{013c}' => 'l',
        '\x{013e}' => 'l',
        '\x{0140}' => 'l',
        '\x{0142}' => 'l',
        '\x{0143}' => 'N',
        '\x{0145}' => 'N',
        '\x{0147}' => 'N',
        '\x{014a}' => 'N',
        '\x{0144}' => 'n',
        '\x{0146}' => 'n',
        '\x{0148}' => 'n',
        '\x{014b}' => 'n',
        '\x{014c}' => 'O',
        '\x{014e}' => 'O',
        '\x{0150}' => 'O',
        '\x{014d}' => 'o',
        '\x{014f}' => 'o',
        '\x{0151}' => 'o',
        '\x{0154}' => 'R',
        '\x{0156}' => 'R',
        '\x{0158}' => 'R',
        '\x{0155}' => 'r',
        '\x{0157}' => 'r',
        '\x{0159}' => 'r',
        '\x{015a}' => 'S',
        '\x{015c}' => 'S',
        '\x{015e}' => 'S',
        '\x{0160}' => 'S',
        '\x{015b}' => 's',
        '\x{015d}' => 's',
        '\x{015f}' => 's',
        '\x{0161}' => 's',
        '\x{0162}' => 'T',
        '\x{0164}' => 'T',
        '\x{0166}' => 'T',
        '\x{0163}' => 't',
        '\x{0165}' => 't',
        '\x{0167}' => 't',
        '\x{0168}' => 'U',
        '\x{016a}' => 'U',
        '\x{016c}' => 'U',
        '\x{016e}' => 'U',
        '\x{0170}' => 'U',
        '\x{0172}' => 'U',
        '\x{0169}' => 'u',
        '\x{016b}' => 'u',
        '\x{016d}' => 'u',
        '\x{016f}' => 'u',
        '\x{0171}' => 'u',
        '\x{0173}' => 'u',
        '\x{0174}' => 'W',
        '\x{0175}' => 'w',
        '\x{0176}' => 'Y',
        '\x{0177}' => 'y',
        '\x{0178}' => 'Y',
        '\x{0179}' => 'Z',
        '\x{017b}' => 'Z',
        '\x{017d}' => 'Z',
        '\x{017a}' => 'z',
        '\x{017c}' => 'z',
        '\x{017e}' => 'z',
        '\x{0132}' => 'IJ',
        '\x{0133}' => 'ij',
        '\x{0152}' => 'Oe',
        '\x{0153}' => 'oe',
        '\x{0149}' => "'n",
        '\x{017f}' => 's',
    ];

    /** Used to compose unicode character classes. */
    private const rsComboMarksRange = '\\x{0300}-\\x{036f}';
    private const reComboHalfMarksRange = '\\x{fe20}-\\x{fe2f}';
    private const rsComboSymbolsRange = '\\x{20d0}-\\x{20ff}';
    private const rsComboRange = self::rsComboMarksRange . self::reComboHalfMarksRange . self::rsComboSymbolsRange;

    /** Used to compose unicode capture groups to match [combining diacritical marks](https =>//en.wikipedia.org/wiki/Combining_Diacritical_Marks) and
     * [combining diacritical marks for symbols](https
     * =>//en.wikipedia.org/wiki/Combining_Diacritical_Marks_for_Symbols).
     * */
    private const rsCombo = '#[' . self::rsComboRange . ']#u';

    /**
     *
     * Pluralize a string
     *
     * @return Transforms|Text
     *
     */
    public function pluralize(): self
    {
        $this->internalString = Inflector::pluralize($this->internalString);
        return $this;
    }

    /**
     *
     * singularize a string
     *
     * @return Transforms|Text
     *
     */
    public function singularize(): self
    {
        $this->internalString = Inflector::singularize($this->internalString);
        return $this;
    }

    /**
     *
     * Remove all accents from string
     *
     * @return Transforms|Text
     *
     */
    public function deburr(): self
    {
        $patterns = \array_map(static fn($pattern) => "#$pattern#u", \array_keys(self::deburredLetters));
        $this->internalString = \preg_replace(self::rsCombo, '', \preg_replace($patterns, \array_values(self::deburredLetters), $this->internalString));

        return $this;
    }

    /**
     *
     * Lowercase
     *
     * @return Transforms|Text
     *
     */
    public function lower(): self
    {
        $this->internalString = mb_strtolower($this->internalString);
        return $this;
    }

    /**
     *
     * Uppercase
     *
     * @return Transforms|Text
     *
     */
    public function upper(): self
    {
        $this->internalString = mb_strtoupper($this->internalString);
        return $this;
    }

    /**
     *
     * Capitalize
     *
     * @param  bool  $firstOnly
     * @return Transforms|Text
     *
     */
    public function capitalize(bool $firstOnly = false): self
    {
        if ($firstOnly) {
            $this->internalString = ucfirst($this->internalString);
        } else {
            $this->internalString = ucwords($this->internalString);
        }

        return $this;
    }

    /**
     *
     * Change string to kebab case
     *
     * @return Transforms|Text
     *
     */
    public function kebab(): self
    {
        $this->internalString = str_replace([' ', '/', '\\', '&'], ['-', '-', '-', '-'], $this->lower()->deburr()->value());
        return $this;
    }

    /**
     *
     * Change string to slug
     *
     * @param  string  $locale
     * @return Transforms|Text
     *
     */
    public function slug(string $locale = 'en'): self
    {
        $slug = new AsciiSlugger($locale, ['en' => ['&' => 'and'], 'fr' => ['&' => 'et']]);
        $this->internalString = $slug->slug($this->internalString)->lower();
        return $this;
    }

    /**
     *
     * camelCase the string
     *
     * @return Transforms|Text
     *
     */
    public function camel(): self
    {
        $this->internalString = (new UnicodeString($this->internalString))->camel();
        return $this;
    }

    /**
     *
     * SnakeCase given string
     *
     * @return Transforms|Text
     *
     */
    public function snake(): self
    {
        $this->internalString = (new UnicodeString($this->internalString))->snake();
        return $this;
    }

    /**
     *
     * Multibyte enabled string reverse
     *
     * @return Transforms|Text
     *
     */
    public function reverse(): self
    {
        $encoding = mb_detect_encoding($this->internalString);
        $length = mb_strlen($this->internalString, $encoding);
        $reversed = '';

        while ($length-- > 0) {
            $reversed .= mb_substr($this->internalString, $length, 1, $encoding);
        }

        $this->internalString = $reversed;
        return $this;
    }

    /**
     *
     * Trim both ends of the string
     *
     * @return Transforms|Text
     *
     */
    public function trim(): self
    {
        $this->internalString = trim($this->internalString);
        return $this;
    }

    /**
     *
     * Trim left end of the string
     *
     * @return Transforms|Text
     *
     */
    public function trimLeft(): self
    {
        $this->internalString = ltrim($this->internalString);
        return $this;
    }

    /**
     *
     * Trim the right end of the string
     *
     * @return Transforms|Text
     *
     */
    public function trimRight(): self
    {
        $this->internalString = rtrim($this->internalString);
        return $this;
    }

    /**
     *
     * Convert binary string to hexadecimal string
     *
     * @return Transforms|Text
     *
     */
    public function hex(): self
    {
        $this->internalString = bin2hex($this->internalString);
        return $this;
    }

    /**
     *
     * Convert hexadecimal string to binary string
     *
     * @return Transforms|Text
     *
     */
    public function bin(): self
    {
        $this->internalString = hex2bin($this->internalString);
        return $this;
    }

    /**
     *
     * Text to binary (like apple to 1100001 1110000....)
     *
     * @return Transforms|Text
     *
     */
    public function binary(): self
    {
        $characters = str_split($this->internalString);

        $binary = [];
        foreach ($characters as $character) {
            $data = unpack('H*', $character);
            $binary[] = base_convert($data[1], 16, 2);
        }

        $this->internalString = implode(' ', $binary);
        return $this;
    }

    /**
     *
     * binary to text
     *
     * @return Transforms|Text
     *
     */
    public function text(): self
    {
        $binaries = explode(' ', $this->internalString);

        $string = null;
        foreach ($binaries as $binary) {
            $string .= pack('H*', dechex(bindec($binary)));
        }

        $this->internalString = $string;
        return $this;
    }

    /**
     *
     * Pad the string up to given length with give character and direction
     *
     * @param  int          $length
     * @param  string|Text  $char
     * @param  int          $direction
     * @return Transforms|Text
     *
     */
    public function pad(int $length, string|Text $char = ' ', int $direction = STR_PAD_RIGHT): self
    {
        $this->internalString = str_pad($this->internalString, $length, $char, $direction);
        return $this;
    }

    /**
     *
     * Replace one or many words in the string, in case-insensitive mode or not
     *
     * @param  array|string  $list
     * @param  array|string  $replacements
     * @param  bool          $insensitive
     * @return Transforms|Text
     *
     */
    public function replace(array|string $list, array|string $replacements, bool $insensitive = false): self
    {
        if ($insensitive) {
            $this->internalString = str_ireplace($list, $replacements, $this->internalString);
            return $this;
        }

        $this->internalString = str_replace($list, $replacements, $this->internalString);
        return $this;
    }

    /**
     *
     * Shuffle the characters in the string
     *
     * @return Transforms|Text
     *
     */
    public function shuffle(): self
    {
        $this->internalString = str_shuffle($this->internalString);
        return $this;
    }

    /**
     *
     * Make string safe to use in database or display to users
     *
     * @return Transforms|Text
     *
     */
    public function safe(): self
    {
        $str = htmlspecialchars(stripslashes(strip_tags($this->internalString)), ENT_QUOTES, 'UTF-8');
        $this->internalString = $str;
        return $this;
    }

    /**
     *
     * Strip tags and allow only given tags
     *
     * @param  array|string  $allowedTags
     * @return Transforms|Text
     *
     */
    public function stripTags(array|string $allowedTags = ''): self
    {
        $this->internalString = strip_tags($this->internalString, $allowedTags);
        return $this;
    }

    /**
     *
     * Split string into substrings
     *
     * @param  string  $breaker
     * @return Collection&string[]
     *
     */
    public function split(string $breaker): Collection
    {
        $list = explode($breaker, $this->internalString);
        return new Collection($list);
    }

    /**
     *
     * Alias for split
     *
     * @param  string  $breaker
     * @return Collection
     *
     */
    public function explode(string $breaker): Collection
    {
        return $this->split($breaker);
    }

    /**
     *
     * Encode special characters
     *
     * @return Transforms|Text
     *
     */
    public function specialChars(): self
    {
        $this->internalString = htmlspecialchars($this->internalString);
        return $this;
    }

    /**
     *
     * encode html entities
     *
     * @return Transforms|Text
     *
     */
    public function entities(): self
    {
        $this->internalString = htmlentities($this->internalString);
        return $this;
    }

    /**
     *
     * Split string in chunks
     *
     * @param  int  $length
     * @return Collection&string[]
     *
     */
    public function chunks(int $length): Collection
    {
        $chunks = mb_str_split($this->internalString, $length, 'UTF-8');
        return new Collection($chunks);
    }

    /**
     *
     * Format a string (sprintf)
     *
     * @param  array|Collection  $values
     * @return Transforms|Text
     *
     */
    public function format(array|Collection $values): self
    {
        $this->internalString = vsprintf($this->internalString, $values);
        return $this;
    }

    /**
     *
     * Truncate a string at requested length (length - length of end string)
     * so this method always returns the length you asked for never the length + length
     * of end string
     *
     * @param  int     $length
     * @param  string  $end
     * @return Transforms|Text
     *
     */
    public function truncate(int $length, string $end = '...'): self
    {
        // Only process if string is longer than required length
        if (mb_strlen($this->internalString) > $length - strlen($end)) {
            $this->internalString = mb_substr($this->internalString, 0, $length - strlen($end)) . $end;
        }

        return $this;
    }

    /**
     *
     * Truncate to the closest word that matches the length (hence safe)
     *
     * @param  int     $count
     * @param  string  $end
     * @return Transforms|Text
     *
     */
    public function safeTruncate(int $count, string $end = '...'): self
    {
        $parts = preg_split('/([\s\n\r]+)/u', $this->internalString, 0, PREG_SPLIT_DELIM_CAPTURE);
        $partsCount = count($parts);
        $addEnd = false;

        $length = 0;
        $last_part = 0;

        for (; $last_part < $partsCount; ++$last_part) {
            $length += strlen($parts[$last_part]);

            if ($length > $count) {
                $addEnd = true;
                break;
            }
        }

        $this->internalString = implode(array_slice($parts, 0, $last_part));

        if ($addEnd) {
            $this->internalString .= $end;
        }

        return $this;
    }

    /**
     *
     * Basename of the given filepath
     *
     * @return Transforms|Text
     *
     */
    public function basename(): self
    {
        $this->internalString = basename($this->internalString);
        return $this;
    }

    /**
     *
     * Add the site url to the url string
     *
     * @return Transforms|Text
     *
     */
    public function url(): self
    {
        $base = 'http://localhost';
        $url = $this->internalString;

        if (function_exists('env')) {
            $base = env('SITE_URL', 'http://localhost');
        }

        // Remove / on base url (if present)
        if (str_ends_with($base, '/')) {
            $base = substr($base, 0, -1);
        }

        // Add slash at start of path (if not present)
        if (!str_starts_with($url, '/')) {
            $url = '/' . $url;
        }

        $this->internalString = $base . $url;
        return $this;
    }

    /**
     *
     * Get extension of file in string (can be full path or just filename)
     *
     * @return Transforms|Text
     *
     */
    public function extension(): self
    {
        $string = $basename = basename($this->internalString);
        $n = strrpos($string, ".");

        if ($n === false) {
            return new self('');
        }

        return new self(substr($string, $n + 1));
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
        $this->internalString = str_replace(PHP_EOL, '<br/>', $this->internalString);
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