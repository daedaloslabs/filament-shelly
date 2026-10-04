<?php

namespace DaedalosLabs\FilamentShelly\Tests;

use DaedalosLabs\FilamentShelly\SensorTypes\Custom;
use DaedalosLabs\FilamentShelly\SensorTypes\DoorWindow;
use DaedalosLabs\FilamentShelly\SensorTypes\HumidityTemperature;
use DaedalosLabs\FilamentShelly\SensorTypes\PowerMeter;
use DaedalosLabs\FilamentShelly\SensorTypes\Temperature;

class SensorTypesTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $status
     * @param  array<string, mixed>  $options
     * @return array<string, string|null>
     */
    private function read(string $type, array $status, array $options = []): array
    {
        $values = [];
        foreach (app($type)->metrics($options) as $metric) {
            $value = $metric->value($status);
            $values[$metric->key] = $value === null ? null : $metric->display($value);
        }

        return $values;
    }

    public function test_temperature_reads_gen2_addon_gen1_and_a_chosen_probe(): void
    {
        $this->assertSame(['temperature' => '27.6 °C'], $this->read(Temperature::class, ['temperature:100' => ['tC' => 27.56]]));
        $this->assertSame(['temperature' => '21.0 °C'], $this->read(Temperature::class, ['tmp' => ['tC' => 21]]));
        $this->assertSame(['temperature' => '30.0 °C'], $this->read(Temperature::class, [
            'temperature:100' => ['tC' => 10], 'temperature:101' => ['tC' => 30],
        ], ['probe' => '101']));
    }

    public function test_humidity_temperature_supports_gen1_and_gen2(): void
    {
        $this->assertSame(['temperature' => '22.4 °C', 'humidity' => '55 %', 'battery' => '80 %'], $this->read(HumidityTemperature::class, [
            'temperature:0' => ['tC' => 22.4], 'humidity:0' => ['rh' => 55.2], 'devicepower:0' => ['battery' => ['percent' => 80]],
        ]));
        $this->assertSame(['temperature' => '19.0 °C', 'humidity' => '40 %', 'battery' => null], $this->read(HumidityTemperature::class, [
            'tmp' => ['tC' => 19], 'hum' => ['value' => 40],
        ]));
    }

    public function test_power_meter_uses_the_channel(): void
    {
        $this->assertSame(
            ['output' => 'On', 'power' => '12.3 W', 'voltage' => '230.0 V', 'energy' => '1.50 kWh'],
            $this->read(PowerMeter::class, ['switch:1' => ['output' => true, 'apower' => 12.34, 'voltage' => 230, 'aenergy' => ['total' => 1500]]], ['channel' => 1]),
        );
    }

    public function test_door_window_understands_both_generations(): void
    {
        $this->assertSame('Open', $this->read(DoorWindow::class, ['window:0' => ['open' => true]])['state']);
        $this->assertSame('Closed', $this->read(DoorWindow::class, ['sensor' => ['state' => 'close']])['state']);
    }

    public function test_custom_maps_user_defined_paths(): void
    {
        $this->assertSame(['wifi_signal' => '-52 dBm'], $this->read(Custom::class, ['wifi' => ['rssi' => -52]], [
            'metrics' => [['label' => 'WiFi signal', 'path' => 'wifi.rssi', 'unit' => 'dBm'], ['label' => '', 'path' => 'x']],
        ]));
    }
}
