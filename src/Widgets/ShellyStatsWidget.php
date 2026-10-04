<?php

namespace DaedalosLabs\FilamentShelly\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Blade;
use DaedalosLabs\FilamentShelly\Models\ShellySensor;
use DaedalosLabs\FilamentShelly\ShellyCloud;
use DaedalosLabs\FilamentShelly\ShellyPlugin;

/**
 * Renders every sensor metric the settings page placed on `$page`.
 * Injected automatically through a render hook; can also be added by hand:
 * `ShellyStatsWidget::make(['page' => static::class])`.
 */
class ShellyStatsWidget extends StatsOverviewWidget
{
    public ?string $page = null;

    public static function renderOn(?string $page): string
    {
        // An optional widget must never take down a page (e.g. migrations not run yet).
        return rescue(function () use ($page): string {
            if ($page === null || ShellySensor::placedOn($page)->isEmpty()) {
                return '';
            }

            return Blade::render('@livewire($widget, ["page" => $page], key("filament-shelly-stats"))', [
                'widget' => static::class,
                'page' => $page,
            ]);
        }, '');
    }

    protected function getPollingInterval(): ?string
    {
        return ShellyPlugin::get()->getPollingInterval();
    }

    protected function getStats(): array
    {
        $plugin = ShellyPlugin::get();
        $sensors = ShellySensor::placedOn((string) $this->page);

        $devices = [];
        foreach ($sensors->groupBy('shelly_account_id') as $accountSensors) {
            $devices += app(ShellyCloud::class)->devices(
                $accountSensors->first()->account,
                $accountSensors->pluck('device_id')->all(),
                $plugin->getCacheSeconds(),
            );
        }

        $stats = [];
        foreach ($sensors as $sensor) {
            $type = $plugin->getSensorType($sensor->type);
            $device = $devices[$sensor->device_id] ?? null;
            $placed = $sensor->metricsPlacedOn((string) $this->page);

            foreach ($type?->metrics($sensor->options ?? []) ?? [] as $metric) {
                if (! in_array($metric->key, $placed, true)) {
                    continue;
                }

                $value = $device ? $metric->value($device['status']) : null;

                $stats[] = Stat::make("{$sensor->name} · {$metric->label}", $value === null ? '—' : $metric->display($value))
                    ->icon($metric->icon)
                    ->description(__('filament-shelly::shelly.status.'.match (true) {
                        $device === null => 'unavailable',
                        ! $device['online'] => 'offline',
                        $value === null => 'no_value',
                        default => 'live',
                    }))
                    ->color($device && $device['online'] && $value !== null ? 'success' : 'gray');
            }
        }

        return $stats;
    }
}
