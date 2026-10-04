<?php

namespace DaedalosLabs\FilamentShelly;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use DaedalosLabs\FilamentShelly\Models\ShellyAccount;

/**
 * Shelly Cloud Control API v2 client.
 *
 * Requests are kept to a minimum: every device status is cached, the devices
 * still missing are fetched together (10 ids per request, the API maximum),
 * and failures are cached too so an offline cloud is not hammered.
 */
class ShellyCloud
{
    public const MAX_IDS_PER_REQUEST = 10;

    public const TIMEOUT_SECONDS = 5;

    /**
     * @param  array<int, string>  $deviceIds
     * @return array<string, array{online: bool, status: array<string, mixed>}|null> null = unavailable
     */
    public function devices(ShellyAccount $account, array $deviceIds, int $cacheSeconds = 60): array
    {
        $deviceIds = array_values(array_unique($deviceIds));

        if (! $account->isConfigured()) {
            return array_fill_keys($deviceIds, null);
        }

        $cacheKeys = array_combine($deviceIds, array_map(fn (string $id): string => $this->cacheKey($account, $id), $deviceIds));

        $cached = Cache::many(array_values($cacheKeys));
        $missing = array_values(array_filter($deviceIds, fn (string $id): bool => $cached[$cacheKeys[$id]] === null));

        if ($missing !== []) {
            $fetched = $this->fetch($account, $missing);

            // `false` marks "unavailable" so it is cached too; a cache miss is `null`.
            $toCache = [];
            foreach ($fetched as $id => $device) {
                $cached[$cacheKeys[$id]] = $toCache[$cacheKeys[$id]] = $device ?? false;
            }
            Cache::putMany($toCache, $cacheSeconds);
        }

        return array_map(fn (string $key): ?array => $cached[$key] ?: null, $cacheKeys);
    }

    /**
     * Uncached request(s) for the given devices.
     *
     * @param  array<int, string>  $deviceIds
     * @return array<string, array{online: bool, status: array<string, mixed>}|null>
     */
    public function fetch(ShellyAccount $account, array $deviceIds): array
    {
        $result = array_fill_keys($deviceIds, null);

        if (! $account->isConfigured()) {
            return $result;
        }

        foreach (array_chunk($deviceIds, self::MAX_IDS_PER_REQUEST) as $i => $chunk) {
            if ($i > 0) {
                // Shelly allows 1 request/second per account, so >10 devices on one page block for 1s per extra batch. Queue a refresh job if that ever matters.
                sleep(1);
            }

            try {
                $response = Http::timeout(self::TIMEOUT_SECONDS)
                    ->retry(2, 1100, fn (\Throwable $e): bool => $e instanceof RequestException && $e->response->status() === 429, throw: false)
                    ->withQueryParameters(['auth_key' => $account->auth_key])
                    ->post("https://{$this->host($account)}/v2/devices/api/get", ['ids' => $chunk, 'select' => ['status']]);
            } catch (ConnectionException) {
                continue;
            }

            $devices = $response->successful() ? $response->json() : null;

            if (! is_array($devices) || ! array_is_list($devices)) {
                continue;
            }

            foreach ($devices as $device) {
                if (is_array($device) && array_key_exists($device['id'] ?? null, $result)) {
                    $result[$device['id']] = [
                        'online' => (bool) ($device['online'] ?? false),
                        'status' => (array) ($device['status'] ?? []),
                    ];
                }
            }
        }

        return $result;
    }

    private function host(ShellyAccount $account): string
    {
        return (string) Str::of((string) $account->server_uri)->trim()->after('://')->rtrim('/');
    }

    private function cacheKey(ShellyAccount $account, string $deviceId): string
    {
        // The key is part of the hash so a device id without its credentials never reads someone else's cache.
        return 'filament-shelly:'.sha1($this->host($account).'|'.$account->auth_key.'|'.$deviceId);
    }
}
