<?php

namespace RoyalMultiGamers\FirewallOVHGames\Filament\Admin\Resources\OvhFirewallIpConfigs\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use RoyalMultiGamers\FirewallOVHGames\Filament\Admin\Resources\OvhFirewallIpConfigs\OvhFirewallIpConfigResource;
use Filament\Notifications\Notification;

class EditOvhFirewallIpConfig extends EditRecord
{
    protected static string $resource = OvhFirewallIpConfigResource::class;

    protected function getFormActions(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->getSaveFormAction()->formId('form')
                ->icon('tabler-device-floppy'),

            Actions\Action::make('sync')
                ->label(__('firewall::firewall.ip_configs.sync_now'))
                ->icon('tabler-refresh')
                ->color('success')
                ->requiresConfirmation()
                ->action(function () {
                    try {
                        $syncService = app(\RoyalMultiGamers\FirewallOVHGames\Services\FirewallSyncService::class);
                        $syncService->syncIpAllocations($this->record->panel_ip);

                        Notification::make()
                            ->title(__('firewall::firewall.ip_configs.sync_success'))
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title(__('firewall::firewall.ip_configs.sync_failed'))
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Actions\DeleteAction::make(),
        ];
    }
}
