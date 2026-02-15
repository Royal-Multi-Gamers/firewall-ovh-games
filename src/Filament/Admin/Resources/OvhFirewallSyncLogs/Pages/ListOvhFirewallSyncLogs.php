<?php

namespace RoyalMultiGamers\FirewallOVHGames\Filament\Admin\Resources\OvhFirewallSyncLogs\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use RoyalMultiGamers\FirewallOVHGames\Filament\Admin\Resources\OvhFirewallSyncLogs\OvhFirewallSyncLogResource;
use RoyalMultiGamers\FirewallOVHGames\Models\OvhFirewallSyncLog;
use Filament\Notifications\Notification;

class ListOvhFirewallSyncLogs extends ListRecords
{
    protected static string $resource = OvhFirewallSyncLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('delete_all_logs')
                ->label(__('firewall::firewall.sync_logs.delete_all'))
                ->icon('tabler-trash-x')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading(__('firewall::firewall.sync_logs.delete_all_confirm'))
                ->modalDescription(__('firewall::firewall.sync_logs.delete_all_description'))
                ->modalSubmitActionLabel(__('firewall::firewall.sync_logs.delete_all_submit'))
                ->action(function () {
                    $deleted = OvhFirewallSyncLog::query()->delete();

                    Notification::make()
                        ->title(__('firewall::firewall.sync_logs.delete_all_success'))
                        ->body(__('firewall::firewall.sync_logs.cleanup_count', ['count' => $deleted]))
                        ->success()
                        ->send();

                    // Refresh the page to show updated list
                    return redirect(static::getUrl());
                }),

            Actions\Action::make('cleanup_old_logs')
                ->label(__('firewall::firewall.sync_logs.cleanup_old'))
                ->icon('tabler-trash')
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription(__('firewall::firewall.sync_logs.cleanup_description'))
                ->action(function () {
                    $deleted = OvhFirewallSyncLog::cleanupOldLogs();

                    Notification::make()
                        ->title(__('firewall::firewall.sync_logs.cleanup_success'))
                        ->body(__('firewall::firewall.sync_logs.cleanup_count', ['count' => $deleted]))
                        ->success()
                        ->send();

                    // Refresh the page to show updated list
                    return redirect(static::getUrl());
                }),
        ];
    }
}
