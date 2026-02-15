<?php

namespace RoyalMultiGamers\FirewallOVHGames\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use App\Models\Role;
use App\Models\Allocation;
use RoyalMultiGamers\FirewallOVHGames\Models\OvhFirewallSetting;
use RoyalMultiGamers\FirewallOVHGames\Models\OvhFirewallIpConfig;
use RoyalMultiGamers\FirewallOVHGames\Models\OvhFirewallSyncLog;
use RoyalMultiGamers\FirewallOVHGames\Services\OvhApiService;
use RoyalMultiGamers\FirewallOVHGames\Services\FirewallSyncService;
use RoyalMultiGamers\FirewallOVHGames\Observers\AllocationObserver;
use RoyalMultiGamers\FirewallOVHGames\Jobs\SyncFirewallRulesJob;
use RoyalMultiGamers\FirewallOVHGames\Commands\SyncFirewallRulesCommand;
use RoyalMultiGamers\FirewallOVHGames\Commands\TestOvhConnectionCommand;

class FirewallOVHGamesPluginProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register custom permissions for the plugin models
        Role::registerCustomDefaultPermissions('ovh_firewall_setting');
        Role::registerCustomModelIcon('ovh_firewall_setting', 'tabler-shield-lock');

        Role::registerCustomDefaultPermissions('ovh_firewall_ip_config');
        Role::registerCustomModelIcon('ovh_firewall_ip_config', 'tabler-network');

        Role::registerCustomDefaultPermissions('ovh_firewall_sync_log');
        Role::registerCustomModelIcon('ovh_firewall_sync_log', 'tabler-file-text');

        // Register services as singletons
        $this->app->singleton(OvhApiService::class, function ($app) {
            return new OvhApiService();
        });

        $this->app->singleton(FirewallSyncService::class, function ($app) {
            return new FirewallSyncService(
                $app->make(OvhApiService::class)
            );
        });

        // Merge plugin configuration
        $this->mergeConfigFrom(
            plugin_path('firewall-ovh-games', 'config/firewall-ovh-games.php'),
            'firewall-ovh-games'
        );
    }

    public function boot(): void
    {
        // Load translations
        $this->loadTranslationsFrom(
            plugin_path('firewall-ovh-games', 'lang'),
            'firewall'
        );

        // Publish configuration
        $this->publishes([
            plugin_path('firewall-ovh-games', 'config/firewall-ovh-games.php') => config_path('firewall-ovh-games.php'),
        ], 'firewall-ovh-games-config');

        // Load and publish migrations
        $this->loadMigrationsFrom(plugin_path('firewall-ovh-games', 'database/migrations'));

        // Register commands (always register, not just in console)
        // This allows Artisan::call() to work from web interface
        $this->commands([
            SyncFirewallRulesCommand::class,
            TestOvhConnectionCommand::class,
            \RoyalMultiGamers\FirewallOVHGames\Commands\SyncNodeIpsCommand::class,
        ]);

        // Only register observer and scheduler if tables exist
        try {
            if (\Schema::hasTable('ovh_firewall_settings')) {
                // Register Allocation observer for automatic sync on events
                $settings = OvhFirewallSetting::getInstance();
                if ($settings->sync_on_events) {
                    Allocation::observe(AllocationObserver::class);
                }

                // Schedule automatic synchronization using the panel's scheduler
                // This ensures it runs with the same cron job as the panel
                $this->app->afterResolving(Schedule::class, function (Schedule $schedule) {
                    try {
                        $settings = OvhFirewallSetting::getInstance();

                        if ($settings->sync_enabled) {
                            $interval = $settings->sync_interval ?? 5;
                            
                            // Queue full sync job via panel scheduler
                            $schedule->job(new SyncFirewallRulesJob(), config('firewall-ovh-games.sync.queue_name', 'default'))
                                ->cron("*/{$interval} * * * *")
                                ->withoutOverlapping();
                        }

                        // Cleanup old sync logs daily to keep admin pages responsive
                        $schedule->call(function () {
                            OvhFirewallSyncLog::cleanupOldLogs();
                        })->dailyAt('03:30')->withoutOverlapping();
                    } catch (\Exception $e) {
                        // Settings not configured yet, skip scheduling
                    }
                });
            }
        } catch (\Exception $e) {
            // Tables don't exist yet, skip observer and scheduler registration
        }
    }
}
