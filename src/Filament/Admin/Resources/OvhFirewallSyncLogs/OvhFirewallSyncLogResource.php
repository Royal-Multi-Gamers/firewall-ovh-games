<?php

namespace RoyalMultiGamers\FirewallOVHGames\Filament\Admin\Resources\OvhFirewallSyncLogs;

use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\TextInput;
use Filament\Schemas\Components\Textarea;
use Filament\Schemas\Components\KeyValue;
use Filament\Schemas\Schema;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Actions\ViewAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\ActionGroup;
use RoyalMultiGamers\FirewallOVHGames\Filament\Admin\Resources\OvhFirewallSyncLogs\Pages;
use RoyalMultiGamers\FirewallOVHGames\Models\OvhFirewallSyncLog;

class OvhFirewallSyncLogResource extends Resource
{
    protected static ?string $model = OvhFirewallSyncLog::class;

    protected static ?int $navigationSort = 3;

    public static function getNavigationIcon(): ?string
    {
        return 'tabler-file-text';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Firewall OVH';
    }

    public static function getNavigationLabel(): string
    {
        return __('firewall::firewall.sync_logs.title');
    }

    public static function getModelLabel(): string
    {
        return __('firewall::firewall.sync_logs.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('firewall::firewall.sync_logs.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('firewall::firewall.sync_logs.log_details'))
                    ->schema([
                        TextInput::make('ip')
                            ->label(__('firewall::firewall.sync_logs.ip'))
                            ->disabled(),

                        TextInput::make('ip_on_game')
                            ->label(__('firewall::firewall.sync_logs.ip_on_game'))
                            ->disabled(),

                        TextInput::make('action')
                            ->label(__('firewall::firewall.sync_logs.action'))
                            ->disabled(),

                        TextInput::make('status')
                            ->label(__('firewall::firewall.sync_logs.status'))
                            ->disabled(),

                        TextInput::make('port')
                            ->label(__('firewall::firewall.sync_logs.port'))
                            ->disabled(),

                        TextInput::make('protocol')
                            ->label(__('firewall::firewall.sync_logs.protocol'))
                            ->disabled(),

                        TextInput::make('rule_id')
                            ->label(__('firewall::firewall.sync_logs.rule_id'))
                            ->disabled(),

                        Textarea::make('message')
                            ->label(__('firewall::firewall.sync_logs.message'))
                            ->disabled()
                            ->columnSpanFull(),

                        Textarea::make('error')
                            ->label(__('firewall::firewall.sync_logs.error'))
                            ->disabled()
                            ->columnSpanFull()
                            ->visible(fn (?OvhFirewallSyncLog $record) => $record && $record->error),

                        KeyValue::make('details')
                            ->label(__('firewall::firewall.sync_logs.details'))
                            ->disabled()
                            ->columnSpanFull()
                            ->visible(fn (?OvhFirewallSyncLog $record) => $record && $record->details),
                    ])
                    ->columns(2),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('firewall::firewall.sync_logs.log_details'))
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        TextEntry::make('ip')
                            ->label(__('firewall::firewall.sync_logs.ip'))
                            ->copyable()
                            ->icon('tabler-server'),

                        TextEntry::make('ip_on_game')
                            ->label(__('firewall::firewall.sync_logs.ip_on_game'))
                            ->copyable()
                            ->icon('tabler-device-gamepad'),

                        TextEntry::make('action')
                            ->label(__('firewall::firewall.sync_logs.action'))
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'add' => 'success',
                                'update' => 'info',
                                'delete' => 'danger',
                                'sync' => 'primary',
                                default => 'gray',
                            }),

                        TextEntry::make('status')
                            ->label(__('firewall::firewall.sync_logs.status'))
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'success' => 'success',
                                'failed' => 'danger',
                                'pending' => 'warning',
                                default => 'gray',
                            }),

                        TextEntry::make('port')
                            ->label(__('firewall::firewall.sync_logs.port'))
                            ->badge()
                            ->color('info'),

                        TextEntry::make('protocol')
                            ->label(__('firewall::firewall.sync_logs.protocol'))
                            ->badge(),

                        TextEntry::make('rule_id')
                            ->label(__('firewall::firewall.sync_logs.rule_id'))
                            ->placeholder(__('firewall::firewall.sync_logs.no_rule_id'))
                            ->copyable(),

                        TextEntry::make('synced_at')
                            ->label(__('firewall::firewall.sync_logs.synced_at'))
                            ->dateTime()
                            ->since(),

                        TextEntry::make('message')
                            ->label(__('firewall::firewall.sync_logs.message'))
                            ->columnSpanFull(),

                        TextEntry::make('error')
                            ->label(__('firewall::firewall.sync_logs.error'))
                            ->columnSpanFull()
                            ->color('danger')
                            ->visible(fn (?OvhFirewallSyncLog $record) => $record && $record->error),

                        TextEntry::make('details')
                            ->label(__('firewall::firewall.sync_logs.details'))
                            ->columnSpanFull()
                            ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT) : $state)
                            ->visible(fn (?OvhFirewallSyncLog $record) => $record && $record->details),
                    ]),
            ]);
    }


    public static function table(Table $table): Table
    {
        return $table
            ->paginated([10, 25, 50])
            ->columns([
                TextColumn::make('ip')
                    ->label(__('firewall::firewall.sync_logs.ip'))
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('port')
                    ->label(__('firewall::firewall.sync_logs.port'))
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),

                TextColumn::make('action')
                    ->label(__('firewall::firewall.sync_logs.action'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'add' => 'success',
                        'update' => 'info',
                        'delete' => 'danger',
                        'sync' => 'primary',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('status')
                    ->label(__('firewall::firewall.sync_logs.status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'success' => 'success',
                        'failed' => 'danger',
                        'pending' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('protocol')
                    ->label(__('firewall::firewall.sync_logs.protocol'))
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('message')
                    ->label(__('firewall::firewall.sync_logs.message'))
                    ->limit(50)
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('synced_at')
                    ->label(__('firewall::firewall.sync_logs.synced_at'))
                    ->dateTime()
                    ->sortable()
                    ->since(),

                TextColumn::make('created_at')
                    ->label(__('firewall::firewall.sync_logs.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOvhFirewallSyncLogs::route('/'),
            'view' => Pages\ViewOvhFirewallSyncLog::route('/{record}'),
        ];
    }

    public static function canAccess(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        return $user?->can('viewList', OvhFirewallSyncLog::class) ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
