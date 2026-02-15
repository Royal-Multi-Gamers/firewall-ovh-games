<?php

namespace RoyalMultiGamers\FirewallOVHGames\Filament\Admin\Pages;

use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithHeaderActions;
use Filament\Pages\Page;
use RoyalMultiGamers\FirewallOVHGames\Jobs\SyncFirewallRulesJob;
use RoyalMultiGamers\FirewallOVHGames\Models\OvhFirewallSetting;
use RoyalMultiGamers\FirewallOVHGames\Services\OvhApiService;

class OvhFirewallSettings extends Page implements HasSchemas
{
    use InteractsWithForms;
    use InteractsWithHeaderActions;

    protected string $view = 'filament.pages.settings';

    protected static ?int $navigationSort = 1;

    public static function getNavigationIcon(): ?string
    {
        return 'tabler-shield-lock';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Firewall OVH';
    }

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('firewall::firewall.settings.title');
    }

    public function getTitle(): string
    {
        return __('firewall::firewall.settings.title');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewList', OvhFirewallSetting::class) ?? false;
    }

    public function mount(): void
    {
        $settings = OvhFirewallSetting::getInstance();
        
        $this->form->fill([
            'application_key' => $settings->application_key,
            'application_secret' => $settings->application_secret,
            'endpoint' => $settings->endpoint,
            'consumer_key' => $settings->consumer_key,
            'sync_enabled' => $settings->sync_enabled,
            'sync_interval' => $settings->sync_interval,
            'sync_on_events' => $settings->sync_on_events,
            'default_protocol' => $settings->default_protocol,
        ]);
    }

    protected function getFormSchema(): array
    {
        return [
            Section::make(__('firewall::firewall.settings.ovh_api_credentials'))
                ->description(__('firewall::firewall.settings.ovh_api_credentials_description'))
                ->schema([
                    TextInput::make('application_key')
                        ->label(__('firewall::firewall.settings.application_key'))
                        ->required()
                        ->maxLength(255)
                        ->helperText(__('firewall::firewall.settings.application_key_help')),

                    TextInput::make('application_secret')
                        ->label(__('firewall::firewall.settings.application_secret'))
                        ->required()
                        ->password()
                        ->revealable()
                        ->maxLength(255)
                        ->helperText(__('firewall::firewall.settings.application_secret_help')),

                    Select::make('endpoint')
                        ->label(__('firewall::firewall.settings.endpoint'))
                        ->required()
                        ->options(OvhFirewallSetting::getAvailableEndpoints())
                        ->default('ovh-eu')
                        ->helperText(__('firewall::firewall.settings.endpoint_help')),

                    TextInput::make('consumer_key')
                        ->label(__('firewall::firewall.settings.consumer_key'))
                        ->required()
                        ->password()
                        ->revealable()
                        ->maxLength(255)
                        ->helperText(__('firewall::firewall.settings.consumer_key_help')),
                ])
                ->columns(2),

            Section::make(__('firewall::firewall.settings.sync_configuration'))
                ->description(__('firewall::firewall.settings.sync_configuration_description'))
                ->schema([
                    Toggle::make('sync_enabled')
                        ->label(__('firewall::firewall.settings.sync_enabled'))
                        ->helperText(__('firewall::firewall.settings.sync_enabled_help'))
                        ->default(true)
                        ->inline(false),

                    TextInput::make('sync_interval')
                        ->label(__('firewall::firewall.settings.sync_interval'))
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->maxValue(60)
                        ->default(5)
                        ->suffix(__('firewall::firewall.settings.minutes'))
                        ->helperText(__('firewall::firewall.settings.sync_interval_help')),

                    Toggle::make('sync_on_events')
                        ->label(__('firewall::firewall.settings.sync_on_events'))
                        ->helperText(__('firewall::firewall.settings.sync_on_events_help'))
                        ->default(true)
                        ->inline(false),
                ])
                ->columns(2),

            Section::make(__('firewall::firewall.settings.firewall_rules'))
                ->description(__('firewall::firewall.settings.firewall_rules_description'))
                ->schema([
                    Select::make('default_protocol')
                        ->label(__('firewall::firewall.settings.default_protocol'))
                        ->required()
                        ->options(OvhFirewallSetting::getAvailableProtocols())
                        ->default('other')
                        ->helperText(__('firewall::firewall.settings.default_protocol_help')),
                ]),
        ];
    }

    protected function getFormStatePath(): ?string
    {
        return 'data';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label(__('firewall::firewall.settings.save'))
                ->icon('tabler-device-floppy')
                ->action('save')
                ->keyBindings(['mod+s']),

            Action::make('test_connection')
                ->label(__('firewall::firewall.settings.test_connection'))
                ->icon('tabler-plug-connected')
                ->color('info')
                ->action('testConnection'),

            Action::make('sync_now')
                ->label(__('firewall::firewall.settings.sync_now'))
                ->icon('tabler-refresh')
                ->color('success')
                ->requiresConfirmation()
                ->action('syncNow'),
        ];
    }

    public function save(): void
    {
        try {
            $data = $this->form->getState();

            $settings = OvhFirewallSetting::getInstance();
            
            // Update or create the settings
            if ($settings->exists) {
                $settings->update($data);
            } else {
                $settings->fill($data);
                $settings->save();
            }

            // Refresh the form with saved data
            $this->form->fill([
                'application_key' => $settings->application_key,
                'application_secret' => $settings->application_secret,
                'endpoint' => $settings->endpoint,
                'consumer_key' => $settings->consumer_key,
                'sync_enabled' => $settings->sync_enabled,
                'sync_interval' => $settings->sync_interval,
                'sync_on_events' => $settings->sync_on_events,
                'default_protocol' => $settings->default_protocol,
            ]);

            Notification::make()
                ->title(__('firewall::firewall.settings.saved'))
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title(__('firewall::firewall.settings.save_failed'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function testConnection(): void
    {
        try {
            $ovhApi = app(OvhApiService::class);
            $ovhApi->testConnection();

            Notification::make()
                ->title(__('firewall::firewall.settings.connection_success'))
                ->body(__('firewall::firewall.settings.connection_success_body'))
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title(__('firewall::firewall.settings.connection_failed'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function syncNow(): void
    {
        try {
            SyncFirewallRulesJob::dispatch()
                ->onQueue(config('firewall-ovh-games.sync.queue_name', 'default'));

            Notification::make()
                ->title(__('firewall::firewall.settings.sync_started'))
                ->body(__('firewall::firewall.settings.sync_started_body'))
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title(__('firewall::firewall.settings.sync_failed'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
