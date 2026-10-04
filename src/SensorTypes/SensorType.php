<?php

namespace DaedalosLabs\FilamentShelly\SensorTypes;

use Filament\Schemas\Components\Component;

/**
 * Extend this to support a new kind of Shelly device, then register it with
 * `ShellyPlugin::make()->sensorTypes([MySensor::class])`.
 */
abstract class SensorType
{
    /** Stored in the database, so never change it once released. */
    abstract public function key(): string;

    abstract public function label(): string;

    /**
     * @param  array<string, mixed>  $options  The values of the fields from form()
     * @return array<int, Metric>
     */
    abstract public function metrics(array $options): array;

    /**
     * Extra per-sensor fields, saved in the sensor's `options`.
     * Make them `->live(onBlur: true)` if metrics() depends on them.
     *
     * @return array<int, Component|\Filament\Forms\Components\Field>
     */
    public function form(): array
    {
        return [];
    }
}
