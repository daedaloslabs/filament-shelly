<?php

namespace DaedalosLabs\FilamentShelly;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use DaedalosLabs\FilamentShelly\Pages\ManageShellySensors;
use DaedalosLabs\FilamentShelly\SensorTypes\Custom;
use DaedalosLabs\FilamentShelly\SensorTypes\DoorWindow;
use DaedalosLabs\FilamentShelly\SensorTypes\HumidityTemperature;
use DaedalosLabs\FilamentShelly\SensorTypes\PowerMeter;
use DaedalosLabs\FilamentShelly\SensorTypes\SensorType;
use DaedalosLabs\FilamentShelly\SensorTypes\Temperature;
use DaedalosLabs\FilamentShelly\Widgets\ShellyStatsWidget;

class ShellyPlugin implements Plugin
{
    /** @var array<int, class-string<SensorType>> */
    protected array $sensorTypes = [
        Temperature::class,
        HumidityTemperature::class,
        PowerMeter::class,
        DoorWindow::class,
        Custom::class,
    ];

    /** @var array<string, SensorType>|null */
    protected ?array $resolvedSensorTypes = null;

    protected int $cacheSeconds = 60;

    protected ?string $pollingInterval = '60s';

    protected string $renderHook = PanelsRenderHook::PAGE_HEADER_WIDGETS_BEFORE;

    protected string|Closure|null $navigationGroup = null;

    protected ?int $navigationSort = null;

    protected ?Closure $authorizeUsing = null;

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static */
        return filament(app(static::class)->getId());
    }

    public function getId(): string
    {
        return 'filament-shelly';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([ManageShellySensors::class]);
    }

    public function boot(Panel $panel): void
    {
        FilamentView::registerRenderHook(
            $this->renderHook,
            fn (array $scopes): string => ShellyStatsWidget::renderOn($scopes[0] ?? null),
        );
    }

    /**
     * Add sensor types. Pass `merge: false` to replace the built-in ones.
     *
     * @param  array<int, class-string<SensorType>>  $types
     */
    public function sensorTypes(array $types, bool $merge = true): static
    {
        $this->sensorTypes = $merge ? [...$this->sensorTypes, ...$types] : $types;
        $this->resolvedSensorTypes = null;

        return $this;
    }

    /**
     * @return array<string, SensorType>
     */
    public function getSensorTypes(): array
    {
        return $this->resolvedSensorTypes ??= collect($this->sensorTypes)
            ->map(fn (string $class): SensorType => app($class))
            ->keyBy(fn (SensorType $type): string => $type->key())
            ->all();
    }

    public function getSensorType(?string $key): ?SensorType
    {
        return $this->getSensorTypes()[$key] ?? null;
    }

    /** How long a device status is reused before Shelly Cloud is asked again. */
    public function cacheFor(int $seconds): static
    {
        $this->cacheSeconds = $seconds;

        return $this;
    }

    public function getCacheSeconds(): int
    {
        return $this->cacheSeconds;
    }

    /** Widget refresh interval, e.g. '30s'. null disables polling. */
    public function pollingInterval(?string $interval): static
    {
        $this->pollingInterval = $interval;

        return $this;
    }

    public function getPollingInterval(): ?string
    {
        return $this->pollingInterval;
    }

    /** Where on each page the stats render, any PanelsRenderHook constant. */
    public function renderHook(string $hook): static
    {
        $this->renderHook = $hook;

        return $this;
    }

    public function navigationGroup(string|Closure|null $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function getNavigationGroup(): ?string
    {
        return value($this->navigationGroup);
    }

    public function navigationSort(?int $sort): static
    {
        $this->navigationSort = $sort;

        return $this;
    }

    public function getNavigationSort(): ?int
    {
        return $this->navigationSort;
    }

    /** Who may open the settings page, e.g. fn () => auth()->user()->isAdmin(). */
    public function authorize(?Closure $callback): static
    {
        $this->authorizeUsing = $callback;

        return $this;
    }

    public function isAuthorized(): bool
    {
        return $this->authorizeUsing === null || (bool) app()->call($this->authorizeUsing);
    }
}
