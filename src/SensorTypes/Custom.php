<?php

namespace DaedalosLabs\FilamentShelly\SensorTypes;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Str;

/**
 * Any device: the user maps status JSON paths to stats in the settings page.
 */
class Custom extends SensorType
{
    public function key(): string
    {
        return 'custom';
    }

    public function label(): string
    {
        return __('filament-shelly::shelly.types.custom');
    }

    public function metrics(array $options): array
    {
        $metrics = [];

        foreach ($options['metrics'] ?? [] as $metric) {
            if (blank($metric['label'] ?? null) || blank($metric['path'] ?? null)) {
                continue;
            }

            $key = Str::slug($metric['label'], '_') ?: md5($metric['label']);
            $metrics[$key] = new Metric($key, $metric['label'], [$metric['path']], unit: $metric['unit'] ?? null, decimals: null);
        }

        return array_values($metrics);
    }

    public function form(): array
    {
        return [
            Repeater::make('metrics')
                ->label(__('filament-shelly::shelly.fields.custom_metrics'))
                ->helperText(__('filament-shelly::shelly.fields.custom_metrics_help'))
                ->schema([
                    TextInput::make('label')->label(__('filament-shelly::shelly.fields.label'))->required()->live(onBlur: true),
                    TextInput::make('path')->label(__('filament-shelly::shelly.fields.path'))->required()->placeholder('temperature:100.tC')->live(onBlur: true),
                    TextInput::make('unit')->label(__('filament-shelly::shelly.fields.unit'))->placeholder('°C'),
                ])
                ->columns(3)
                ->minItems(1)
                ->defaultItems(1)
                ->live(),
        ];
    }
}
