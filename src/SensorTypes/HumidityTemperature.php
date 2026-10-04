<?php

namespace DaedalosLabs\FilamentShelly\SensorTypes;

/**
 * Shelly H&T (Gen1, Plus, Gen3) and similar climate sensors.
 */
class HumidityTemperature extends SensorType
{
    public function key(): string
    {
        return 'humidity_temperature';
    }

    public function label(): string
    {
        return __('filament-shelly::shelly.types.humidity_temperature');
    }

    public function metrics(array $options): array
    {
        return [
            new Metric('temperature', __('filament-shelly::shelly.metrics.temperature'), ['temperature:0.tC', 'tmp.tC'], unit: '°C', icon: 'heroicon-o-fire'),
            new Metric('humidity', __('filament-shelly::shelly.metrics.humidity'), ['humidity:0.rh', 'hum.value'], unit: '%', icon: 'heroicon-o-cloud', decimals: 0),
            new Metric('battery', __('filament-shelly::shelly.metrics.battery'), ['devicepower:0.battery.percent', 'bat.value'], unit: '%', icon: 'heroicon-o-battery-50', decimals: 0),
        ];
    }
}
