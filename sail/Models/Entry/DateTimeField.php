<?php

namespace SailCMS\Models\Entry;

use SailCMS\Collection;
use SailCMS\Types\Fields\InputDateField;
use SailCMS\Types\Fields\InputTimeField;
use SailCMS\Types\StoringType;

class DateTimeField extends Field
{
    const DATE_TIME_ARE_REQUIRED = '6145: Date and time are required since one of them is';

    /**
     *
     * Description for date time field
     *
     * @return string
     */
    public function description(): string
    {
        return 'Field to implement a date and a time html inputs';
    }

    /**
     *
     * How to parse the content of the field
     *
     * @return string
     *
     */
    public function storingType(): string
    {
        return StoringType::ARRAY->value;
    }

    /**
     *
     * Default values
     *
     * @return Collection
     *
     */
    public function defaultSettings(): Collection
    {
        return new Collection([
            'date' => InputDateField::defaultSettings(),
            'time' => InputTimeField::defaultSettings()
        ]);
    }

    /**
     *
     * Base configs
     *
     * @return void
     *
     */
    protected function defineBaseConfigs(): void
    {
        $this->baseConfigs = new Collection([
            'date' => InputDateField::class,
            'time' => InputTimeField::class
        ]);

    }

    /**
     *
     * The validation of the inputs
     *
     * @param  Collection  $content
     * @return Collection|null
     *
     */
    protected function validate(Collection $content): ?Collection
    {
        // If one input is required all began required to avoid parsing
        if ($this->isRequired() && (!$content->get('date') || !$content->get('time'))) {
            return new Collection([
                self::DATE_TIME_ARE_REQUIRED
            ]);
        }
        return null;
    }

    /**
     *
     * Combine the date and time values et convert them to timestamp
     *
     * @param  mixed  $content
     * @return mixed
     *
     */
    public function convert(mixed $content): mixed
    {
        $dateFormat = $this->configs->get('date.format') ?? DateField::DATE_FORMAT_DEFAULT;
        $dateRaw = $content->get('date') ?? '';

        $timeFormat = $this->configs->get('time.format') ?? TimeField::TIME_FORMAT_DEFAULT;
        $timeRaw = $content->get('time') ?? '';

        $dateTime = \DateTime::createFromFormat($dateFormat . " " . $timeFormat, $dateRaw . " " . $timeRaw,);

        return $dateTime->getTimestamp();
    }

    /**
     *
     * Ungroup inputs into two separate values
     *
     * @param  mixed  $content
     * @return Collection
     *
     */
    public function parse(mixed $content): Collection
    {
        $dateFormat = $this->configs->get('date.format') ?? DateField::DATE_FORMAT_DEFAULT;
        $timeFormat = $this->configs->get('time.format') ?? TimeField::TIME_FORMAT_DEFAULT;
        $date = new \DateTime();
        $date = $date->setTimestamp($content);

        return new Collection([
            'date' => $date->format($dateFormat),
            'time' => $date->format($timeFormat)
        ]);
    }
}