<?php

namespace RoyalMultiGamers\FirewallOVHGames\Filament\Admin\Resources\OvhFirewallIpConfigs;

use App\Models\Node;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Actions\ActionGroup;
use RoyalMultiGamers\FirewallOVHGames\Models\OvhFirewallIpConfig;
use RoyalMultiGamers\FirewallOVHGames\Filament\Admin\Resources\OvhFirewallIpConfigs\Pages;
use Illuminate\Support\Facades\Artisan;
use Filament\Notifications\Notification;

class OvhFirewallIpConfigResource extends Resource
{
    protected static ?string $model = OvhFirewallIpConfig::class;

    protected static ?int $navigationSort = 2;

    public static function getNavigationIcon(): ?string
    {
        return 'tabler-network';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Firewall OVH';
    }

    public static function getNavigationLabel(): string
    {
        return __('firewall::firewall.ip_configs.title');
    }

    public static function getModelLabel(): string
    {
        return __('firewall::firewall.ip_configs.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('firewall::firewall.ip_configs.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('firewall::firewall.ip_configs.node_information'))
                    ->schema([
                        Select::make('node_id')
                            ->label(__('firewall::firewall.ip_configs.node'))
                            ->relationship('node', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->helperText(__('firewall::firewall.ip_configs.node_help')),

                        TextInput::make('panel_ip')
                            ->label(__('firewall::firewall.ip_configs.panel_ip'))
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->helperText(__('firewall::firewall.ip_configs.panel_ip_help')),
                    ])
                    ->columns(2),

                Section::make(__('firewall::firewall.ip_configs.ovh_mapping'))
                    ->schema([
                        TextInput::make('ovh_ip')
                            ->label(__('firewall::firewall.ip_configs.ovh_ip'))
                            ->required()
                            ->helperText(__('firewall::firewall.ip_configs.ovh_ip_help')),

                        TextInput::make('ovh_ip_on_game')
                            ->label(__('firewall::firewall.ip_configs.ovh_ip_on_game'))
                            ->required()
                            ->helperText(__('firewall::firewall.ip_configs.ovh_ip_on_game_help')),
                    ])
                    ->columns(2),

                Section::make(__('firewall::firewall.ip_configs.configuration'))
                    ->schema([
                        Toggle::make('enabled')
                            ->label(__('firewall::firewall.ip_configs.enabled'))
                            ->helperText(__('firewall::firewall.ip_configs.enabled_help'))
                            ->default(true)
                            ->inline(false),

                        Textarea::make('notes')
                            ->label(__('firewall::firewall.ip_configs.notes'))
                            ->rows(3)
                            ->helperText(__('firewall::firewall.ip_configs.notes_help')),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->paginated([10, 25, 50])
            ->columns([
                TextColumn::make('node.name')
                    ->label(__('firewall::firewall.ip_configs.node'))
                    ->searchable()
                    ->sortable()
                    ->default(__('firewall::firewall.ip_configs.no_node')),

                TextColumn::make('panel_ip')
                    ->label(__('firewall::firewall.ip_configs.panel_ip'))
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->icon('tabler-server'),

                TextColumn::make('ovh_ip')
                    ->label(__('firewall::firewall.ip_configs.ovh_ip'))
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->icon('tabler-cloud'),

                TextColumn::make('ovh_ip_on_game')
                    ->label(__('firewall::firewall.ip_configs.ovh_ip_on_game'))
                    ->searchable()
                    ->copyable()
                    ->icon('tabler-device-gamepad'),

                IconColumn::make('enabled')
                    ->label(__('firewall::firewall.ip_configs.enabled'))
                    ->boolean()
                    ->sortable(),

                TextColumn::make('last_synced_at')
                    ->label(__('firewall::firewall.ip_configs.last_synced'))
                    ->dateTime()
                    ->sortable()
                    ->placeholder(__('firewall::firewall.ip_configs.never_synced')),

                TextColumn::make('created_at')
                    ->label(__('firewall::firewall.ip_configs.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('enabled')
                    ->label(__('firewall::firewall.ip_configs.status'))
                    ->options([
                        '1' => __('firewall::firewall.ip_configs.enabled'),
                        '0' => __('firewall::firewall.ip_configs.disabled'),
                    ]),

                SelectFilter::make('node_id')
                    ->label(__('firewall::firewall.ip_configs.node'))
                    ->relationship('node', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOvhFirewallIpConfigs::route('/'),
            'create' => Pages\CreateOvhFirewallIpConfig::route('/create'),
            'edit' => Pages\EditOvhFirewallIpConfig::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with('node');
    }
}
