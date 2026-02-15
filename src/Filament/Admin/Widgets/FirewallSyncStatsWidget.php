<?php

namespace RoyalMultiGamers\FirewallOVHGames\Filament\Admin\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use RoyalMultiGamers\FirewallOVHGames\Models\OvhFirewallIpConfig;
use RoyalMultiGamers\FirewallOVHGames\Models\OvhFirewallSyncLog;
use RoyalMultiGamers\FirewallOVHGames\Models\OvhFirewallSetting;
use App\Models\Allocation;

class FirewallSyncStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $settings = OvhFirewallSetting::getInstance();
        
        // Get configured IPs count
        $configuredIps = OvhFirewallIpConfig::enabled()->count();
        
        // Get total allocations with servers
        $totalAllocations = Allocation::whereNotNull('server_id')->count();
        
        // Get recent sync stats (last 24 hours)
        $recentLogs = OvhFirewallSyncLog::where('created_at', '>=', now()->subDay());
        $successfulSyncs = (clone $recentLogs)->where('status', 'success')->count();
        $failedSyncs = (clone $recentLogs)->where('status', 'failed')->count();
        
        // Get last sync time
        $lastSync = OvhFirewallSyncLog::where('status', 'success')
            ->orderBy('synced_at', 'desc')
            ->first();

        return [
            Stat::make(__('firewall::firewall.widgets.configured_ips'), $configuredIps)
                ->description(__('firewall::firewall.widgets.configured_ips_description'))
                ->descriptionIcon('tabler-network')
                ->color('primary'),

            Stat::make(__('firewall::firewall.widgets.active_allocations'), $totalAllocations)
                ->description(__('firewall::firewall.widgets.active_allocations_description'))
                ->descriptionIcon('tabler-server')
                ->color('info'),

            Stat::make(__('firewall::firewall.widgets.successful_syncs'), $successfulSyncs)
                ->description(__('firewall::firewall.widgets.last_24h'))
                ->descriptionIcon('tabler-check')
                ->color('success'),

            Stat::make(__('firewall::firewall.widgets.failed_syncs'), $failedSyncs)
                ->description(__('firewall::firewall.widgets.last_24h'))
                ->descriptionIcon('tabler-x')
                ->color($failedSyncs > 0 ? 'danger' : 'gray'),

            Stat::make(__('firewall::firewall.widgets.last_sync'), 
                $lastSync ? $lastSync->synced_at->diffForHumans() : __('firewall::firewall.widgets.never')
            )
                ->description(__('firewall::firewall.widgets.last_successful_sync'))
                ->descriptionIcon('tabler-clock')
                ->color($lastSync && $lastSync->synced_at->isToday() ? 'success' : 'warning'),

            Stat::make(__('firewall::firewall.widgets.sync_status'), 
                $settings->sync_enabled 
                    ? __('firewall::firewall.widgets.enabled') 
                    : __('firewall::firewall.widgets.disabled')
            )
                ->description(__('firewall::firewall.widgets.automatic_sync'))
                ->descriptionIcon($settings->sync_enabled ? 'tabler-check' : 'tabler-x')
                ->color($settings->sync_enabled ? 'success' : 'danger'),
        ];
    }

    public static function canView(): bool
    {
        return auth()->user()?->can('viewList', OvhFirewallSetting::class) ?? false;
    }
}
