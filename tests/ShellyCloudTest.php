<?php

namespace DaedalosLabs\FilamentShelly\Tests;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use DaedalosLabs\FilamentShelly\Models\ShellyAccount;
use DaedalosLabs\FilamentShelly\ShellyCloud;

class ShellyCloudTest extends TestCase
{
    private function account(): ShellyAccount
    {
        return new ShellyAccount(['server_uri' => 'https://shelly-103-eu.shelly.cloud/', 'auth_key' => 'secret']);
    }

    public function test_it_fetches_devices_in_one_request_and_caches_them(): void
    {
        Http::fake(['*' => Http::response([
            ['id' => 'aaa', 'online' => 1, 'status' => ['temperature:100' => ['tC' => 27.5]]],
            ['id' => 'bbb', 'online' => 0, 'status' => []],
        ])]);

        $cloud = app(ShellyCloud::class);
        $devices = $cloud->devices($this->account(), ['aaa', 'bbb', 'ccc', 'aaa']);

        $this->assertSame(27.5, $devices['aaa']['status']['temperature:100']['tC']);
        $this->assertTrue($devices['aaa']['online']);
        $this->assertFalse($devices['bbb']['online']);
        $this->assertNull($devices['ccc']); // not returned by the cloud

        // Second call, including the unknown device, is served from cache.
        $cloud->devices($this->account(), ['aaa', 'bbb', 'ccc']);

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://shelly-103-eu.shelly.cloud/v2/devices/api/get?auth_key=secret')
            && $request['ids'] === ['aaa', 'bbb', 'ccc']
            && $request['select'] === ['status']);
    }

    public function test_it_batches_ten_ids_per_request(): void
    {
        Http::fake(['*' => Http::response([])]);

        $ids = array_map(fn (int $i): string => "dev{$i}", range(1, 11));
        app(ShellyCloud::class)->devices($this->account(), $ids);

        Http::assertSentCount(2);
    }

    public function test_failures_are_cached_as_unavailable(): void
    {
        Http::fake(['*' => Http::response(['error' => 'UNAUTHORIZED'], 401)]);

        $cloud = app(ShellyCloud::class);
        $this->assertSame(['aaa' => null], $cloud->devices($this->account(), ['aaa']));
        $cloud->devices($this->account(), ['aaa']);

        Http::assertSentCount(1);
    }

    public function test_it_sends_nothing_without_credentials(): void
    {
        Http::fake();

        $this->assertSame(['aaa' => null], app(ShellyCloud::class)->devices(new ShellyAccount, ['aaa']));

        Http::assertNothingSent();
    }
}
