<?php

namespace DaedalosLabs\FilamentShelly\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use DaedalosLabs\FilamentShelly\Models\ShellyAccount;
use DaedalosLabs\FilamentShelly\ShellyCloud;
use DaedalosLabs\FilamentShelly\ShellyPlugin;

/**
 * @property-read Schema $form
 */
class ManageShellySensors extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-signal';

    protected static ?string $slug = 'shelly-sensors';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    protected ?ShellyAccount $account = null;

    public static function canAccess(): bool
    {
        return ShellyPlugin::get()->isAuthorized();
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-shelly::shelly.page.title');
    }

    public static function getNavigationGroup(): ?string
    {
        return ShellyPlugin::get()->getNavigationGroup();
    }

    public static function getNavigationSort(): ?int
    {
        return ShellyPlugin::get()->getNavigationSort();
    }

    public function getTitle(): string
    {
        return __('filament-shelly::shelly.page.title');
    }

    protected function getAccount(): ShellyAccount
    {
        // One Shelly Cloud account per app (per tenant database); sensors already belong to an account, so multi-account is a UI change only.
        return $this->account ??= ShellyAccount::query()->firstOrCreate();
    }

    public function mount(): void
    {
        $account = $this->getAccount();
        $authKey = rescue(fn () => $account->auth_key, report: false);

        $this->form->fill(['server_uri' => $account->server_uri, 'auth_key' => $authKey]);

        if ($authKey === null && filled($account->getRawOriginal('auth_key'))) {
            Notification::make()
                ->warning()
                ->title(__('filament-shelly::shelly.notifications.unreadable_key.title'))
                ->body(__('filament-shelly::shelly.notifications.unreadable_key.body'))
                ->persistent()
                ->send();
        }
    }

    public function save(): void
    {
        $data = $this->form->getState(); // also saves the sensors repeater

        $account = $this->getAccount();
        // Drop the stored key from "original" so an undecryptable old value is never compared, just overwritten.
        $account->setRawAttributes(Arr::except($account->getAttributes(), 'auth_key'), sync: true);
        $account->fill(Arr::only($data, ['server_uri', 'auth_key']))->save();

        Notification::make()->success()->title(__('filament-shelly::shelly.notifications.saved'))->send();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')
                            ->label(__('filament-shelly::shelly.page.save'))
                            ->submit('save')
                            ->keyBindings(['mod+s']),
                    ]),
                ]),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $plugin = ShellyPlugin::get();

        return $schema
            ->model($this->getAccount())
            ->statePath('data')
            ->components([
                Section::make(__('filament-shelly::shelly.page.account'))
                    ->description(__('filament-shelly::shelly.page.account_help'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('server_uri')
                            ->label(__('filament-shelly::shelly.fields.server_uri'))
                            ->prefix('https://')
                            ->placeholder('shelly-103-eu.shelly.cloud')
                            ->required()
                            ->dehydrateStateUsing(fn (?string $state): string => (string) Str::of((string) $state)->trim()->after('://')->rtrim('/')),
                        TextInput::make('auth_key')
                            ->label(__('filament-shelly::shelly.fields.auth_key'))
                            ->password()
                            ->revealable()
                            ->required(),
                    ]),

                Section::make(__('filament-shelly::shelly.page.sensors'))
                    ->description(__('filament-shelly::shelly.page.sensors_help'))
                    ->schema([
                        Repeater::make('sensors')
                            ->hiddenLabel()
                            ->relationship()
                            ->orderColumn('sort')
                            ->collapsible()
                            ->cloneable()
                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                            ->addActionLabel(__('filament-shelly::shelly.page.add_sensor'))
                            ->extraItemActions([$this->testSensorAction()])
                            ->columns(3)
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('filament-shelly::shelly.fields.name'))
                                    ->placeholder(__('filament-shelly::shelly.fields.name_placeholder'))
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true),
                                TextInput::make('device_id')
                                    ->label(__('filament-shelly::shelly.fields.device_id'))
                                    ->helperText(__('filament-shelly::shelly.fields.device_id_help'))
                                    ->placeholder('e4b063d6c0c4')
                                    ->required()
                                    ->maxLength(64)
                                    ->dehydrateStateUsing(fn (?string $state): string => Str::lower(trim((string) $state))),
                                Select::make('type')
                                    ->label(__('filament-shelly::shelly.fields.type'))
                                    ->options(collect($plugin->getSensorTypes())->map->label()->all())
                                    ->required()
                                    ->native(false)
                                    ->live()
                                    ->afterStateUpdated(function (Set $set): void {
                                        $set('options', []);
                                        $set('placements', []);
                                    }),
                                Group::make(fn (Get $get): array => $plugin->getSensorType($get('type'))?->form() ?? [])
                                    ->statePath('options')
                                    ->columnSpanFull(),
                                Fieldset::make(__('filament-shelly::shelly.page.placements'))
                                    ->visible(fn (Get $get): bool => filled($get('type')))
                                    ->columns(2)
                                    ->columnSpanFull()
                                    ->schema(fn (Get $get): array => $this->placementFields($get('type'), (array) $get('options'))),
                            ]),
                    ]),
            ]);
    }

    /**
     * One "show on pages" select per metric the chosen sensor type reports.
     *
     * @param  array<string, mixed>  $options
     * @return array<int, Select>
     */
    protected function placementFields(?string $type, array $options): array
    {
        $metrics = ShellyPlugin::get()->getSensorType($type)?->metrics($options) ?? [];

        return array_map(fn ($metric): Select => Select::make("placements.{$metric->key}")
            ->label($metric->label)
            ->placeholder(__('filament-shelly::shelly.page.hidden'))
            ->options($this->pageOptions())
            ->multiple()
            ->native(false), $metrics);
    }

    /**
     * Every page of the current panel, including resource pages.
     *
     * @return array<string, array<class-string, string>>
     */
    protected function pageOptions(): array
    {
        return once(function (): array {
            $panel = Filament::getCurrentOrDefaultPanel();
            $pagesGroup = __('filament-shelly::shelly.page.pages_group');
            $options = [$pagesGroup => []];

            foreach ($panel->getPages() as $page) {
                if ($page !== static::class) {
                    $options[$pagesGroup][$page] = $page::getNavigationLabel();
                }
            }

            foreach ($panel->getResources() as $resource) {
                foreach ($resource::getPages() as $name => $registration) {
                    $options[$resource::getPluralModelLabel()][$registration->getPage()] = Str::headline($name);
                }
            }

            return array_map(fn (array $group): array => Arr::map($group, fn (string $label): string => Str::ucfirst($label)), array_filter($options));
        });
    }

    protected function testSensorAction(): Action
    {
        return Action::make('test')
            ->label(__('filament-shelly::shelly.test.label'))
            ->icon('heroicon-m-signal')
            ->color('gray')
            ->action(function (array $arguments, Repeater $component): void {
                $item = $component->getRawItemState($arguments['item']);
                $deviceId = Str::lower(trim((string) ($item['device_id'] ?? '')));
                $account = new ShellyAccount([
                    'server_uri' => $this->data['server_uri'] ?? null,
                    'auth_key' => $this->data['auth_key'] ?? null,
                ]);

                $device = $deviceId === '' ? null : app(ShellyCloud::class)->fetch($account, [$deviceId])[$deviceId];

                if ($device === null) {
                    Notification::make()->danger()
                        ->title(__('filament-shelly::shelly.test.failed'))
                        ->body(__('filament-shelly::shelly.test.failed_help'))
                        ->send();

                    return;
                }

                $lines = [];
                foreach (ShellyPlugin::get()->getSensorType($item['type'] ?? null)?->metrics((array) ($item['options'] ?? [])) ?? [] as $metric) {
                    $value = $metric->value($device['status']);
                    $lines[] = e($metric->label).': <b>'.e($value === null ? '—' : $metric->display($value)).'</b>';
                }
                $lines[] = __('filament-shelly::shelly.test.components').': <code>'.e(implode(', ', array_keys($device['status']))).'</code>';

                Notification::make()
                    ->success()
                    ->title(__('filament-shelly::shelly.test.'.($device['online'] ? 'online' : 'offline')))
                    ->body(implode('<br>', $lines))
                    ->persistent()
                    ->send();
            });
    }
}
