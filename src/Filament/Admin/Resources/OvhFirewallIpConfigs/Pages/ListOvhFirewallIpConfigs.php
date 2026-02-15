<?php

namespace RoyalMultiGamers\FirewallOVHGames\Filament\Admin\Resources\OvhFirewallIpConfigs\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Notifications\Notification;
use RoyalMultiGamers\FirewallOVHGames\Filament\Admin\Resources\OvhFirewallIpConfigs\OvhFirewallIpConfigResource;
use RoyalMultiGamers\FirewallOVHGames\Models\OvhFirewallIpConfig;

class ListOvhFirewallIpConfigs extends ListRecords
{
    protected static string $resource = OvhFirewallIpConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('tabler-plus'),

            Action::make('sync_from_nodes')
                ->label(__('firewall::firewall.ip_configs.sync_from_nodes'))
                ->icon('tabler-refresh')
                ->color('info')
                ->action(function () {
                    try {
                        $result = OvhFirewallIpConfig::syncFromNodes();

                        if (count($result['created']) > 0) {
                            Notification::make()
                                ->title(__('firewall::firewall.ip_configs.sync_success'))
                                ->body(__('firewall::firewall.ip_configs.sync_success_body', [
                                    'count' => count($result['created']),
                                    'total' => $result['total_node_ips'],
                                ]))
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title(__('firewall::firewall.ip_configs.sync_no_new'))
                                ->body(__('firewall::firewall.ip_configs.sync_no_new_body', [
                                    'total' => $result['total_node_ips'],
                                ]))
                                ->info()
                                ->send();
                        }

                        // Refresh the table
                        $this->dispatch('$refresh');
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title(__('firewall::firewall.ip_configs.sync_failed'))
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
