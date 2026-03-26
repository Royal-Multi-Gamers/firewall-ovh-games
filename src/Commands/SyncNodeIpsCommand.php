<?php

namespace RoyalMultiGamers\FirewallOVHGames\Commands;

use Illuminate\Console\Command;
use RoyalMultiGamers\FirewallOVHGames\Models\OvhFirewallIpConfig;

class SyncNodeIpsCommand extends Command
{
    protected $signature = 'firewall:sync-node-ips';

    protected $description = 'Sync IP addresses from Pelican Panel nodes to firewall IP configurations';

    public function handle(): int
    {
        $this->info('Syncing IP addresses from nodes...');

        try {
            $result = OvhFirewallIpConfig::syncFromNodes();

            $this->info("Total node IPs found: {$result['total_node_ips']}");

            if (($result['filtered_count'] ?? 0) > 0) {
                $this->comment('Ignored ' . $result['filtered_count'] . ' Docker/private IP(s).');
            }
            
            if (count($result['created']) > 0) {
                $this->info('Created ' . count($result['created']) . ' new IP configuration(s):');
                foreach ($result['created'] as $ip) {
                    $this->line("  - $ip");
                }
            } else {
                $this->info('No new IPs to add.');
            }

            if (count($result['skipped']) > 0) {
                $this->comment('Skipped ' . count($result['skipped']) . ' existing IP(s).');
            }

            $this->newLine();
            $this->warn('Note: New IP configurations are disabled by default.');
            $this->warn('Please configure OVH IP mappings in the admin panel and enable them.');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to sync node IPs: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
