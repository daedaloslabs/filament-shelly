<?php

namespace DaedalosLabs\FilamentShelly\SensorTypes;

use Filament\Forms\Components\TextInput;

/**
 * Relays and plugs with power metering: Shelly Plus/Pro 1PM, 2PM, Plug S, PM Mini, Gen1 Plug/1PM.
 */
class PowerMeter extends SensorType
{
    public function key(): string
    {
        return 'power_meter';
    }

    public function label(): string
    {
        return __('filament-shelly::shelly.types.power_meter');
    }

    public function metrics(array $options): array
    {
        $c = (int) ($options['channel'] ?? 0);

        return [
            new Metric('output', __('filament-shelly::shelly.metrics.output'), ["switch:{$c}.output", "relays.{$c}.ison"], icon: 'heroicon-o-power'),
            new Metric('power', __('filament-shelly::shelly.metrics.power'), ["switch:{$c}.apower", "pm1:{$c}.apower", "meters.{$c}.power"], unit: 'W', icon: 'heroicon-o-bolt'),
            new Metric('voltage', __('filament-shelly::shelly.metrics.voltage'), ["switch:{$c}.voltage", "pm1:{$c}.voltage"], unit: 'V', icon: 'heroicon-o-bolt'),
            new Metric('energy', __('filament-shelly::shelly.metrics.energy'), ["switch:{$c}.aenergy.total", "pm1:{$c}.aenergy.total"], icon: 'heroicon-o-chart-bar',
                format: fn (mixed $wh): string => number_format((float) $wh / 1000, 2).' kWh'),
        ];
    }

    public function form(): array
    {
        return [
            TextInput::make('channel')
                ->label(__('filament-shelly::shelly.fields.channel'))
                ->helperText(__('filament-shelly::shelly.fields.channel_help'))
                ->integer()
                ->minValue(0)
                ->default(0)
                ->live(onBlur: true),
        ];
    }
}
