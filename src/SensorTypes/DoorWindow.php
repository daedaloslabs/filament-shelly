<?php

namespace DaedalosLabs\FilamentShelly\SensorTypes;

/**
 * Shelly Door/Window 2 and BLU Door/Window (via gateway) contact sensors.
 */
class DoorWindow extends SensorType
{
    public function key(): string
    {
        return 'door_window';
    }

    public function label(): string
    {
        return __('filament-shelly::shelly.types.door_window');
    }

    public function metrics(array $options): array
    {
        return [
            new Metric('state', __('filament-shelly::shelly.metrics.state'), ['window:0.open', 'sensor.state'], icon: 'heroicon-o-home',
                // Gen2+ reports a bool, Gen1 the strings "open" / "close".
                format: fn (mixed $open): string => __(in_array($open, [true, 'open'], true) ? 'filament-shelly::shelly.values.open' : 'filament-shelly::shelly.values.closed')),
            new Metric('illuminance', __('filament-shelly::shelly.metrics.illuminance'), ['illuminance:0.lux', 'lux.value'], unit: 'lx', icon: 'heroicon-o-sun', decimals: 0),
            new Metric('battery', __('filament-shelly::shelly.metrics.battery'), ['devicepower:0.battery.percent', 'bat.value'], unit: '%', icon: 'heroicon-o-battery-50', decimals: 0),
        ];
    }
}
