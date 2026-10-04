<?php

namespace DaedalosLabs\FilamentShelly\SensorTypes;

use Filament\Forms\Components\TextInput;

/**
 * A temperature probe, e.g. a DS18B20 on a Shelly Plus Add-on (pool / water / pipe thermometer).
 */
class Temperature extends SensorType
{
    public function key(): string
    {
        return 'temperature';
    }

    public function label(): string
    {
        return __('filament-shelly::shelly.types.temperature');
    }

    public function metrics(array $options): array
    {
        $probe = $options['probe'] ?? null;

        return [
            new Metric('temperature', __('filament-shelly::shelly.metrics.temperature'), array_filter([
                filled($probe) ? "temperature:{$probe}.tC" : null,
                filled($probe) ? "ext_temperature.{$probe}.tC" : null,
                'temperature:100.tC',
                'temperature:0.tC',
                'ext_temperature.0.tC',
                'tmp.tC',
            ]), unit: '°C', icon: 'heroicon-o-fire'),
        ];
    }

    public function form(): array
    {
        return [
            TextInput::make('probe')
                ->label(__('filament-shelly::shelly.fields.probe'))
                ->helperText(__('filament-shelly::shelly.fields.probe_help'))
                ->integer()
                ->minValue(0)
                ->live(onBlur: true),
        ];
    }
}
