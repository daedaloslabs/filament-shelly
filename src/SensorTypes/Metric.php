<?php

namespace DaedalosLabs\FilamentShelly\SensorTypes;

use Closure;

/**
 * One value a sensor reports, read from the device status JSON.
 */
final readonly class Metric
{
    /**
     * @param  string  $key  Stable identifier, used to store where the metric is shown.
     * @param  array<int, string>  $paths  Dot paths into the device status; the first non-null wins (lets one type cover Gen1 and Gen2+ devices).
     * @param  ?int  $decimals  Round numbers to this many decimals; null shows the raw value.
     * @param  ?Closure(mixed): string  $format  Full control over the displayed value.
     */
    public function __construct(
        public string $key,
        public string $label,
        public array $paths,
        public ?string $unit = null,
        public ?string $icon = null,
        public ?int $decimals = 1,
        public ?Closure $format = null,
    ) {}

    /**
     * @param  array<string, mixed>  $status
     */
    public function value(array $status): mixed
    {
        foreach ($this->paths as $path) {
            $value = data_get($status, $path);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    public function display(mixed $value): string
    {
        if ($this->format) {
            return ($this->format)($value);
        }

        if (is_bool($value)) {
            return __($value ? 'filament-shelly::shelly.values.on' : 'filament-shelly::shelly.values.off');
        }

        if (is_numeric($value) && $this->decimals !== null) {
            $value = number_format((float) $value, $this->decimals);
        }

        $value = is_scalar($value) ? (string) $value : json_encode($value);

        return trim($value.' '.$this->unit);
    }
}
