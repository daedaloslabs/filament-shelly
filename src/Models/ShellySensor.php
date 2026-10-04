<?php

namespace DaedalosLabs\FilamentShelly\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $name
 * @property string $device_id
 * @property string $type
 * @property ?array<string, mixed> $options
 * @property ?array<string, array<int, string>> $placements metric key => page classes
 */
class ShellySensor extends Model
{
    protected $table = 'shelly_sensors';

    protected $fillable = ['shelly_account_id', 'name', 'device_id', 'type', 'options', 'placements', 'sort'];

    protected function casts(): array
    {
        return ['options' => 'array', 'placements' => 'array'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ShellyAccount::class, 'shelly_account_id');
    }

    /**
     * Metric keys of this sensor that should be shown on the given page.
     *
     * @return array<int, string>
     */
    public function metricsPlacedOn(string $page): array
    {
        return array_keys(array_filter(
            $this->placements ?? [],
            fn (mixed $pages): bool => in_array($page, (array) $pages, true),
        ));
    }

    /**
     * Sensors that have at least one metric placed on the given page. One query per request.
     *
     * @return Collection<int, static>
     */
    public static function placedOn(string $page): Collection
    {
        $all = once(fn () => static::query()->with('account')->orderBy('sort')->get());

        return $all->filter(fn (self $sensor): bool => $sensor->metricsPlacedOn($page) !== [])->values();
    }
}
