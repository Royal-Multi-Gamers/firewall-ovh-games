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
    public static bool $isLazy = true;

    protected function getStats(): array
    {
        $stats = cache()->remember('firewall.stats', 60, function () {
            $settings = OvhFirewallSetting::getInstance();
            
            $configuredIps = OvhFirewallIpConfig::enabled()->count();
            $totalAllocations = Allocation::whereNotNull('server_id')->count();
            
            $recentLogs = OvhFirewallSyncLog::where('created_at', '>=', now()->subDay())
                ->selectRaw('status, COUNT(*) as cnt')
                ->groupBy('status')
                ->pluck('cnt', 'status');
            
            $successfulSyncs = $recentLogs->get('success', 0);
            $failedSyncs = $recentLogs->get('failed', 0);
            
            $lastSync = OvhFirewallSyncLog::where('status', 'success')
                ->orderByDesc('synced_at')
                ->limit(1)
                ->first();

            return [
                'settings' => $settings,
                'configured_ips' => $configuredIps,
                'total_allocations' => $totalAllocations,
                'successful_syncs' => $successfulSyncs,
                'failed_syncs' => $failedSyncs,
                'last_sync' => $lastSync,
            ];
        });

        return [
            Stat::make(__('firewall::firewall.widgets.configured_ips'), $stats['configured_ips'])
                ->description(__('firewall::firewall.widgets.configured_ips_description'))
                ->descriptionIcon('tabler-network')
                ->color('primary'),

            Stat::make(__('firewall::firewall.widgets.active_allocations'), $stats['total_allocations'])
                ->description(__('firewall::firewall.widgets.active_allocations_description'))
                ->descriptionIcon('tabler-server')
                ->color('info'),

            Stat::make(__('firewall::firewall.widgets.successful_syncs'), $stats['successful_syncs'])
                ->description(__('firewall::firewall.widgets.last_24h'))
                ->descriptionIcon('tabler-check')
                ->color('success'),

            Stat::make(__('firewall::firewall.widgets.failed_syncs'), $stats['failed_syncs'])
                ->description(__('firewall::firewall.widgets.last_24h'))
                ->descriptionIcon('tabler-x')
                ->color($stats['failed_syncs'] > 0 ? 'danger' : 'gray'),

            Stat::make(__('firewall::firewall.widgets.last_sync'), 
                $stats['last_sync'] ? $stats['last_sync']->synced_at->diffForHumans() : __('firewall::firewall.widgets.never')
            )
                ->description(__('firewall::firewall.widgets.last_successful_sync'))
                ->descriptionIcon('tabler-clock')
                ->color($stats['last_sync'] && $stats['last_sync']->synced_at->isToday() ? 'success' : 'warning'),

            Stat::make(__('firewall::firewall.widgets.sync_status'), 
                $stats['settings']->sync_enabled 
                    ? __('firewall::firewall.widgets.enabled') 
                    : __('firewall::firewall.widgets.disabled')
            )
                ->description(__('firewall::firewall.widgets.automatic_sync'))
                ->descriptionIcon($stats['settings']->sync_enabled ? 'tabler-check' : 'tabler-x')
                ->color($stats['settings']->sync_enabled ? 'success' : 'danger'),
        ];
    }

    public static function canView(): bool
    {
        return auth()->user()?->can('viewList', OvhFirewallSetting::class) ?? false;
    }
}
