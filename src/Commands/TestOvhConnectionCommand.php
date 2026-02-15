<?php

namespace RoyalMultiGamers\FirewallOVHGames\Commands;

use Illuminate\Console\Command;
use RoyalMultiGamers\FirewallOVHGames\Services\OvhApiService;
use RoyalMultiGamers\FirewallOVHGames\Models\OvhFirewallSetting;
use RoyalMultiGamers\FirewallOVHGames\Models\OvhFirewallIpConfig;

class TestOvhConnectionCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'firewall:test-connection
                            {--ip= : Test connection for a specific IP configuration}';

    /**
     * The console command description.
     */
    protected $description = 'Test the connection to OVH API and verify firewall access';

    /**
     * Execute the console command.
     */
    public function handle(OvhApiService $ovhApi): int
    {
        $this->info('Testing OVH API connection...');
        $this->newLine();

        $settings = OvhFirewallSetting::getInstance();

        // Check if credentials are configured
        if (!$settings->hasCredentials()) {
            $this->error('OVH API credentials are not configured.');
            $this->info('Please configure the following in the admin panel:');
            $this->line('  - Application Key');
            $this->line('  - Application Secret');
            $this->line('  - Consumer Key');
            $this->line('  - Endpoint');
            return self::FAILURE;
        }

        // Display current configuration
        $this->info('Current Configuration:');
        $this->table(
            ['Setting', 'Value'],
            [
                ['Endpoint', $settings->endpoint],
                ['Application Key', $this->maskCredential($settings->application_key)],
                ['Application Secret', $this->maskCredential($settings->application_secret)],
                ['Consumer Key', $this->maskCredential($settings->consumer_key)],
                ['Default Protocol', $settings->default_protocol],
            ]
        );
        $this->newLine();

        // Test basic connection
        $this->info('Testing basic API connection...');
        try {
            $ovhApi->testConnection();
            $this->info('✓ Successfully connected to OVH API');
        } catch (\Exception $e) {
            $this->error('✗ Failed to connect to OVH API');
            $this->error('Error: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->newLine();

        // Test firewall access for specific IP or all IPs
        $specificIp = $this->option('ip');

        if ($specificIp) {
            return $this->testSpecificIp($ovhApi, $specificIp);
        } else {
            return $this->testAllIps($ovhApi);
        }
    }

    /**
     * Test a specific IP configuration.
     */
    protected function testSpecificIp(OvhApiService $ovhApi, string $ip): int
    {
        $ipConfig = OvhFirewallIpConfig::getForPanelIp($ip);

        if (!$ipConfig) {
            $this->error("No OVH configuration found for IP: {$ip}");
            return self::FAILURE;
        }

        $this->info("Testing firewall access for IP: {$ip}");
        $this->line("  OVH IP: {$ipConfig->ovh_ip}");
        $this->line("  OVH IP on Game: {$ipConfig->ovh_ip_on_game}");
        $this->newLine();

        return $this->testFirewallAccess($ovhApi, $ipConfig);
    }

    /**
     * Test all IP configurations.
     */
    protected function testAllIps(OvhApiService $ovhApi): int
    {
        $ipConfigs = OvhFirewallIpConfig::enabled()->get();

        if ($ipConfigs->isEmpty()) {
            $this->warn('No enabled IP configurations found.');
            $this->info('Please configure IP mappings in the admin panel.');
            return self::FAILURE;
        }

        $this->info("Testing firewall access for {$ipConfigs->count()} IP configuration(s)...");
        $this->newLine();

        $allSuccess = true;

        foreach ($ipConfigs as $ipConfig) {
            $this->info("Testing: {$ipConfig->panel_ip}");
            $result = $this->testFirewallAccess($ovhApi, $ipConfig);
            
            if ($result !== self::SUCCESS) {
                $allSuccess = false;
            }

            $this->newLine();
        }

        if ($allSuccess) {
            $this->info('✓ All IP configurations tested successfully!');
            return self::SUCCESS;
        } else {
            $this->warn('Some IP configurations failed. Check the output above for details.');
            return self::FAILURE;
        }
    }

    /**
     * Test firewall access for an IP configuration.
     */
    protected function testFirewallAccess(OvhApiService $ovhApi, OvhFirewallIpConfig $ipConfig): int
    {
        try {
            $rules = $ovhApi->getRules($ipConfig->ovh_ip, $ipConfig->ovh_ip_on_game);
            
            $this->info("  ✓ Successfully accessed firewall rules");
            $this->line("  Found {count($rules)} existing rule(s)");

            if (!empty($rules)) {
                $this->line('  Sample rules:');
                foreach (array_slice($rules, 0, 3) as $rule) {
                    $ports = $rule['ports'] ?? 'N/A';
                    $protocol = $rule['protocol'] ?? 'N/A';
                    $this->line("    - Port: {$ports}, Protocol: {$protocol}");
                }

                if (count($rules) > 3) {
                    $this->line('    ... and ' . (count($rules) - 3) . ' more');
                }
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("  ✗ Failed to access firewall rules");
            $this->error("  Error: " . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Mask a credential for display.
     */
    protected function maskCredential(?string $credential): string
    {
        if (empty($credential)) {
            return 'Not set';
        }

        $length = strlen($credential);
        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        return substr($credential, 0, 4) . str_repeat('*', $length - 8) . substr($credential, -4);
    }
}
