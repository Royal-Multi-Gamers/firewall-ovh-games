<?php

namespace RoyalMultiGamers\FirewallOVHGames\Filament\Admin\Resources\OvhFirewallSyncLogs\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use RoyalMultiGamers\FirewallOVHGames\Filament\Admin\Resources\OvhFirewallSyncLogs\OvhFirewallSyncLogResource;

class ViewOvhFirewallSyncLog extends ViewRecord
{
    protected static string $resource = OvhFirewallSyncLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
