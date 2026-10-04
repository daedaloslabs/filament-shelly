<?php

namespace DaedalosLabs\FilamentShelly\Tests;

use Filament\Actions\Testing\TestAction;
use Filament\Pages\Dashboard;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use DaedalosLabs\FilamentShelly\Models\ShellyAccount;
use DaedalosLabs\FilamentShelly\Models\ShellySensor;
use DaedalosLabs\FilamentShelly\Pages\ManageShellySensors;
use DaedalosLabs\FilamentShelly\Widgets\ShellyStatsWidget;

class SettingsPageTest extends TestCase
{
    public function test_it_saves_the_account_and_sensors(): void
    {
        $page = Livewire::test(ManageShellySensors::class)
            ->assertOk()
            ->fillForm([
                'server_uri' => 'https://shelly-103-eu.shelly.cloud/',
                'auth_key' => 'secret',
                'sensors' => [[
                    'name' => 'Pool',
                    'device_id' => ' E4B063D6C0C4 ',
                    'type' => 'temperature',
                ]],
            ]);

        $item = array_key_first($page->get('data.sensors'));
        $page->assertFormFieldExists("sensors.{$item}.placements.temperature")
            ->fillForm(["sensors.{$item}.placements.temperature" => [Dashboard::class]])
            ->call('save')
            ->assertHasNoFormErrors();

        $account = ShellyAccount::sole();
        $this->assertSame('shelly-103-eu.shelly.cloud', $account->server_uri);
        $this->assertSame('secret', $account->auth_key);

        $sensor = ShellySensor::sole();
        $this->assertSame('e4b063d6c0c4', $sensor->device_id);
        $this->assertSame(['temperature' => [Dashboard::class]], $sensor->placements);
    }

    public function test_it_recovers_from_an_undecryptable_key(): void
    {
        ShellyAccount::query()->insert(['server_uri' => 'x.shelly.cloud', 'auth_key' => 'not-encrypted']);

        Livewire::test(ManageShellySensors::class)
            ->assertNotified()
            ->fillForm(['auth_key' => 'new'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('new', ShellyAccount::sole()->auth_key);
    }

    public function test_the_widget_shows_only_metrics_placed_on_the_page(): void
    {
        $account = ShellyAccount::create(['server_uri' => 'eu.shelly.cloud', 'auth_key' => 'secret']);
        $account->sensors()->create([
            'name' => 'Office', 'device_id' => 'abc', 'type' => 'humidity_temperature',
            'placements' => ['temperature' => [Dashboard::class], 'humidity' => ['Other\\Page']],
        ]);

        Http::fake(['*' => Http::response([
            ['id' => 'abc', 'online' => true, 'status' => ['temperature:0' => ['tC' => 21.4], 'humidity:0' => ['rh' => 50]]],
        ])]);

        $this->assertSame('', ShellyStatsWidget::renderOn('Nothing\\Here'));
        $this->assertStringContainsString('wire:', ShellyStatsWidget::renderOn(Dashboard::class));

        Livewire::test(ShellyStatsWidget::class, ['page' => Dashboard::class, 'lazy' => false])
            ->assertSee('Office · Temperature')
            ->assertSee('21.4 °C')
            ->assertDontSee('Humidity');
    }

    public function test_the_test_button_reports_live_values_and_components(): void
    {
        Http::fake(['*' => Http::response([
            ['id' => 'abc', 'online' => true, 'status' => ['temperature:100' => ['tC' => 27.5], 'wifi' => []]],
        ])]);

        $page = Livewire::test(ManageShellySensors::class)->fillForm([
            'server_uri' => 'eu.shelly.cloud',
            'auth_key' => 'secret',
            'sensors' => [['name' => 'Pool', 'device_id' => 'abc', 'type' => 'temperature']],
        ]);

        $page->callAction(TestAction::make('test')->schemaComponent('sensors')->arguments(['item' => array_key_first($page->get('data.sensors'))]))
            ->assertNotified('Device is online');

        Http::assertSentCount(1);
    }
}
