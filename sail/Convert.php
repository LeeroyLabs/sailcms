<?php

namespace SailCMS;

use DOMException;
use League\Csv\CannotInsertRecord;
use League\Csv\Exception;
use League\Csv\HTMLConverter;
use League\Csv\Reader;
use League\Csv\Writer;
use SailCMS\Errors\ConvertException;
use stdClass;

class Convert
{
    /**
     *
     * CSV to array/object
     *
     * @param  string  $data
     * @param  bool    $hasHeading
     * @param  bool    $object
     * @return array
     * @throws ConvertException
     *
     */
    public static function csv2array(string $data, bool $hasHeading = true, bool $object = false): array
    {
        try {
            $csv = Reader::createFromString($data);

            if ($hasHeading) {
                $csv->setHeaderOffset(0);
            }

            $list = [];
            foreach ($csv->getRecords() as $record) {
                if ($object) {
                    $list[] = (object)$record;
                } else {
                    $list[] = $record;
                }
            }

            return $list;
        } catch (\Exception $e) {
            throw new ConvertException('Cannot convert: ' . $e->getMessage(), 0500);
        }
    }

    /**
     *
     * Short alias for csv2array to return array of objects
     *
     * @param  string  $data
     * @param  bool    $hasHeading
     * @return array
     * @throws ConvertException
     *
     */
    public static function csv2object(string $data, bool $hasHeading = true): array
    {
        return self::csv2array($data, $hasHeading, true);
    }

    /**
     *
     * Convert array to CSV
     *
     * @param  array|Collection|null  $heading
     * @param  array|Collection       $data
     * @return string
     * @throws ConvertException
     *
     */
    public static function toCSV(array|Collection|null $heading, array|Collection $data = []): string
    {
        try {
            if (is_object($data)) {
                $data = $data->unwrap();
            }

            if ($heading && is_object($heading)) {
                $heading = $heading->unwrap();
            }

            $writer = Writer::createFromString();

            if ($heading) {
                $writer->insertOne($heading);
            }

            $writer->insertAll($data);
            return $writer->toString();
        } catch (CannotInsertRecord|\Exception $e) {
            throw new ConvertException('Cannot convert: ' . $e->getMessage(), 0500);
        }
    }

    /**
     *
     * CSV to HTML
     *
     * @param  string            $csv
     * @param  array|Collection  $headings
     * @return string
     * @throws ConvertException
     *
     */
    public static function csv2html(string $csv, array|Collection $headings = []): string
    {
        try {
            if (is_object($headings)) {
                $headings = $headings->unwrap();
            }

            $data = self::csv2array($csv);

            $converter = (new HTMLConverter())->table('table-csv-data', 'csvdoc')
                                              ->tr('data-record-offset')
                                              ->td('title');

            return $converter->convert($data, $headings);
        } catch (\Exception|DOMException $e) {
            throw new ConvertException('Cannot convert: ' . $e->getMessage(), 0500);
        }
    }

    /**
     *
     * CSV to JSON
     *
     * @param  string  $csv
     * @param  bool    $headings
     * @return string
     * @throws ConvertException
     *
     */
    public static function csv2json(string $csv, bool $headings = true): string
    {
        try {
            $data = self::csv2array($csv);
            return json_encode($data, JSON_THROW_ON_ERROR);
        } catch (\Exception|\JsonException $e) {
            throw new ConvertException('Cannot convert: ' . $e->getMessage(), 0500);
        }
    }

    /**
     *
     * From string to base64
     *
     * @param  string  $string
     * @return string
     *
     */
    public static function toBase64(string $string): string
    {
        return base64_encode($string);
    }

    /**
     *
     * From base64 to string
     *
     * @param  string  $b64
     * @return string
     *
     */
    public static function fromBase64(string $b64): string
    {
        return base64_decode($b64);
    }
}