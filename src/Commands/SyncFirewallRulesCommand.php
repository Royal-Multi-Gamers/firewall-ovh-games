<?php

namespace RoyalMultiGamers\FirewallOVHGames\Commands;

use Illuminate\Console\Command;
use RoyalMultiGamers\FirewallOVHGames\Services\FirewallSyncService;
use RoyalMultiGamers\FirewallOVHGames\Models\OvhFirewallIpConfig;
use RoyalMultiGamers\FirewallOVHGames\Models\OvhFirewallSetting;

class SyncFirewallRulesCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'firewall:sync
                            {--ip= : Sync only a specific IP address}
                            {--force : Force sync even if sync is disabled}
                            {--dry-run : Show what would be synced without making changes}';

    /**
     * The console command description.
     */
    protected $description = 'Synchronize OVH firewall rules with Pelican Panel allocations';

    /**
     * Execute the console command.
     */
    public function handle(FirewallSyncService $syncService): int
    {
        $settings = OvhFirewallSetting::getInstance();

        // Check if sync is enabled
        if (!$settings->sync_enabled && !$this->option('force')) {
            $this->error('Firewall synchronization is disabled. Use --force to override.');
            return self::FAILURE;
        }

        // Check if credentials are configured
        if (!$settings->hasCredentials()) {
            $this->error('OVH API credentials are not configured. Please configure them in the admin panel.');
            return self::FAILURE;
        }

        $this->info('Starting firewall synchronization...');
        $this->newLine();

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        try {
            $specificIp = $this->option('ip');

            if ($specificIp) {
                // Sync specific IP
                $this->info("Syncing IP: {$specificIp}");
                $results = $this->syncSpecificIp($syncService, $specificIp);
            } else {
                // Sync all IPs
                $this->info('Syncing all configured IPs...');
                $results = $syncService->syncAllAllocations();
            }

            $this->newLine();
            $this->displayResults($results);

            if ($results['failed'] > 0) {
                $this->warn('Some operations failed. Check the logs for details.');
                return self::FAILURE;
            }

            $this->info('Synchronization completed successfully!');
            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Synchronization failed: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return self::FAILURE;
        }
    }

    /**
     * Sync a specific IP.
     */
    protected function syncSpecificIp(FirewallSyncService $syncService, string $ip): array
    {
        $ipConfig = OvhFirewallIpConfig::getForPanelIp($ip);

        if (!$ipConfig) {
            $this->error("No OVH configuration found for IP: {$ip}");
            return [
                'total' => 0,
                'success' => 0,
                'failed' => 1,
                'skipped' => 0,
            ];
        }

        if (!$ipConfig->isReadyForSync()) {
            $this->error("IP configuration for {$ip} is not ready for sync (disabled or incomplete)");
            return [
                'total' => 0,
                'success' => 0,
                'failed' => 0,
                'skipped' => 1,
            ];
        }

        return $syncService->syncIpAllocations($ip);
    }

    /**
     * Display synchronization results.
     */
    protected function displayResults(array $results): void
    {
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Operations', $results['total']],
                ['Successful', $results['success']],
                ['Failed', $results['failed']],
                ['Skipped', $results['skipped']],
            ]
        );

        if (isset($results['added'])) {
            $this->info("Rules Added: {$results['added']}");
        }

        if (isset($results['removed'])) {
            $this->info("Rules Removed: {$results['removed']}");
        }

        if (isset($results['updated'])) {
            $this->info("Rules Updated: {$results['updated']}");
        }

        // Display per-IP details if available
        if (isset($results['details']) && is_array($results['details'])) {
            $this->newLine();
            $this->info('Per-IP Results:');
            
            foreach ($results['details'] as $ip => $ipResults) {
                if (isset($ipResults['error'])) {
                    $this->error("  {$ip}: {$ipResults['error']}");
                } else {
                    $success = $ipResults['success'] ?? 0;
                    $failed = $ipResults['failed'] ?? 0;
                    $added = $ipResults['added'] ?? 0;
                    $removed = $ipResults['removed'] ?? 0;
                    
                    $this->line("  {$ip}: Success: {$success}, Failed: {$failed}, Added: {$added}, Removed: {$removed}");
                }
            }
        }
    }
}
