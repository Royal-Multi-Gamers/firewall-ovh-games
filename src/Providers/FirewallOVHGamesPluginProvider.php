<?php

namespace RoyalMultiGamers\FirewallOVHGames\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Models\Role;
use App\Models\Allocation;
use RoyalMultiGamers\FirewallOVHGames\Models\OvhFirewallSetting;
use RoyalMultiGamers\FirewallOVHGames\Models\OvhFirewallIpConfig;
use RoyalMultiGamers\FirewallOVHGames\Models\OvhFirewallSyncLog;
use RoyalMultiGamers\FirewallOVHGames\Services\OvhApiService;
use RoyalMultiGamers\FirewallOVHGames\Services\FirewallSyncService;
use RoyalMultiGamers\FirewallOVHGames\Observers\AllocationObserver;
use RoyalMultiGamers\FirewallOVHGames\Jobs\SyncFirewallRulesJob;

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
        // Translations, migrations and commands are auto-registered by the panel.
        // Restart queue workers after any plugin lifecycle command so workers
        // pick up newly installed/removed/toggled plugin code automatically.
        Event::listen(CommandFinished::class, function (CommandFinished $event) {
            $lifecycleCommands = [
                'p:plugin:install',
                'p:plugin:uninstall',
                'p:plugin:enable',
                'p:plugin:disable',
            ];

            if (in_array($event->command, $lifecycleCommands, true)) {
                Artisan::call('optimize:clear');
                Artisan::call('filament:optimize');
                Artisan::call('queue:restart');
            }
        });

        // Only register observer and scheduler if tables exist
        try {
            if (Schema::hasTable('ovh_firewall_settings')) {
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
                            $interval = max(1, (int) ($settings->sync_interval ?? 5));

                            // "*/60" is not a valid minute step: run hourly instead.
                            $cron = $interval >= 60 ? '0 * * * *' : "*/{$interval} * * * *";

                            // Queue full sync job via panel scheduler
                            $schedule->job(new SyncFirewallRulesJob(), config('firewall-ovh-games.sync.queue_name', 'default'))
                                ->cron($cron)
                                ->withoutOverlapping(10);
                        }

                        // Cleanup old sync logs daily to keep admin pages responsive
                        $schedule->call(function () {
                            OvhFirewallSyncLog::cleanupOldLogs();
                        })->dailyAt('03:30')->withoutOverlapping();
                    } catch (\Throwable $e) {
                        Log::debug('Firewall OVH Games: scheduling skipped', ['error' => $e->getMessage()]);
                    }
                });
            }
        } catch (\Throwable $e) {
            // Database unavailable (e.g. before migrations), skip observer and scheduler registration
        }
    }
}
