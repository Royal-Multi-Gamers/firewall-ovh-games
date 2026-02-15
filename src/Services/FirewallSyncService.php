<?php

namespace RoyalMultiGamers\FirewallOVHGames\Services;

use App\Models\Allocation;
use RoyalMultiGamers\FirewallOVHGames\Models\OvhFirewallIpConfig;
use RoyalMultiGamers\FirewallOVHGames\Models\OvhFirewallSyncLog;
use RoyalMultiGamers\FirewallOVHGames\Models\OvhFirewallSetting;
use RoyalMultiGamers\FirewallOVHGames\Exceptions\FirewallSyncException;
use RoyalMultiGamers\FirewallOVHGames\Helpers\PortRangeHelper;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class FirewallSyncService
{
    protected OvhApiService $ovhApi;
    protected OvhFirewallSetting $settings;

    public function __construct(OvhApiService $ovhApi)
    {
        $this->ovhApi = $ovhApi;
        $this->settings = OvhFirewallSetting::getInstance();
    }

    /**
     * Synchronize all allocations with OVH firewall rules.
     */
    public function syncAllAllocations(): array
    {
        if ($this->shouldLogDetailed()) {
            Log::info('Starting full firewall synchronization');
        }

        $results = [
            'total' => 0,
            'success' => 0,
            'failed' => 0,
            'skipped' => 0,
            'details' => [],
        ];

        // Get all unique IPs from allocations that have an enabled OVH configuration
        $configuredIps = $this->getConfiguredPanelIps();
        $ips = Allocation::select('ip')
            ->distinct()
            ->pluck('ip')
            ->intersect($configuredIps)
            ->values();

        foreach ($ips as $ip) {
            try {
                $ipResult = $this->syncIpAllocations($ip);
                $results['total'] += $ipResult['total'];
                $results['success'] += $ipResult['success'];
                $results['failed'] += $ipResult['failed'];
                $results['skipped'] += $ipResult['skipped'];
                $results['details'][$ip] = $ipResult;
            } catch (\Exception $e) {
                Log::error('Failed to sync IP allocations', [
                    'ip' => $ip,
                    'error' => $e->getMessage(),
                ]);

                $results['failed']++;
                $results['details'][$ip] = [
                    'error' => $e->getMessage(),
                ];
            }
        }

        if ($this->shouldLogDetailed()) {
            Log::info('Completed full firewall synchronization', $results);
        }

        return $results;
    }

    /**
     * Synchronize allocations for a specific IP.
     */
    public function syncIpAllocations(string $ip): array
    {
        if ($this->shouldLogDetailed()) {
            Log::info('Syncing allocations for IP', ['ip' => $ip]);
        }

        // Get IP configuration
        $ipConfig = $this->resolveIpConfig($ip);

        if (!$ipConfig) {
            if ($this->shouldLogDetailed()) {
                Log::info('Skipping IP without OVH configuration', ['ip' => $ip]);
            }
            
            return [
                'total' => 0,
                'success' => 0,
                'failed' => 0,
                'skipped' => 1,
                'message' => 'No OVH configuration found',
            ];
        }

        if (!$this->isIpConfigReady($ipConfig)) {
            Log::warning('IP configuration not ready for sync', ['ip' => $ip]);
            
            return [
                'total' => 0,
                'success' => 0,
                'failed' => 0,
                'skipped' => 1,
                'message' => 'IP configuration not ready',
            ];
        }

        $results = [
            'total' => 0,
            'success' => 0,
            'failed' => 0,
            'skipped' => 0,
            'added' => 0,
            'removed' => 0,
            'updated' => 0,
        ];

        try {
            // Get panel allocations for this IP
            $panelPorts = $this->getPanelPorts($ip);
            
            // Get OVH firewall rules
            $ovhRules = $this->ovhApi->getRules(
                $this->getIpConfigValue($ipConfig, 'ovh_ip'),
                $this->getIpConfigValue($ipConfig, 'ovh_ip_on_game')
            );
            $ovhPorts = $this->extractPortsFromRules($ovhRules);

            // Add missing rules
            $missingPorts = $panelPorts->diff($ovhPorts);

            if ($this->shouldLogDetailed()) {
                Log::info('Firewall ports comparison', [
                    'ip' => $ip,
                    'panel_ports' => $panelPorts->values()->all(),
                    'ovh_ports' => $ovhPorts->values()->all(),
                    'missing_ports' => $missingPorts->values()->all(),
                ]);
            }

            foreach ($missingPorts as $port) {
                $results['total']++;
                if ($this->addRule($ipConfig, $port)) {
                    $results['success']++;
                    $results['added']++;
                } else {
                    $results['failed']++;
                }
            }

            // Remove unused rules
            $unusedPorts = $ovhPorts->diff($panelPorts);
            foreach ($unusedPorts as $port) {
                $results['total']++;
                $rule = $this->findRuleByPort($ovhRules, $port);
                if ($rule && $this->removeRule($ipConfig, $rule['id'], $port)) {
                    $results['success']++;
                    $results['removed']++;
                } else {
                    $results['failed']++;
                }
            }

            // Update last synced timestamp
            $this->markIpConfigSynced($ipConfig);

            if ($this->shouldLogDetailed()) {
                Log::info('Completed IP allocation sync', array_merge(['ip' => $ip], $results));
            }
        } catch (\Exception $e) {
            Log::error('Failed to sync IP allocations', [
                'ip' => $ip,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        return $results;
    }

    /**
     * Synchronize a specific allocation.
     */
    public function syncAllocation(Allocation $allocation): bool
    {
        if ($this->shouldLogDetailed()) {
            Log::info('Syncing single allocation', [
                'allocation_id' => $allocation->id,
                'ip' => $allocation->ip,
                'port' => $allocation->port,
            ]);
        }

        // Get IP configuration
        $ipConfig = $this->resolveIpConfig($allocation->ip);

        if (!$ipConfig || !$this->isIpConfigReady($ipConfig)) {
            Log::warning('IP configuration not ready for sync', [
                'ip' => $allocation->ip,
            ]);
            
            return false;
        }

        $isPersistedAllocation = (bool) $allocation->id
            && Allocation::whereKey($allocation->id)->exists();

        if ($isPersistedAllocation) {
            return $this->addOrUpdateRule($ipConfig, $allocation->port);
        }

        return $this->removeRuleIfExists($ipConfig, $allocation->port);
    }

    public function syncAllocationPortChange(Allocation $allocation, int $oldPort): bool
    {
        if ($this->shouldLogDetailed()) {
            Log::info('Syncing allocation port change', [
                'allocation_id' => $allocation->id,
                'ip' => $allocation->ip,
                'old_port' => $oldPort,
                'new_port' => $allocation->port,
            ]);
        }

        $ipConfig = $this->resolveIpConfig($allocation->ip);

        if (!$ipConfig || !$this->isIpConfigReady($ipConfig)) {
            Log::warning('IP configuration not ready for port change sync', [
                'ip' => $allocation->ip,
                'old_port' => $oldPort,
                'new_port' => $allocation->port,
            ]);

            return false;
        }

        if (!$this->removeRuleIfExists($ipConfig, $oldPort)) {
            return false;
        }

        if (!$this->waitForRuleDeletion($ipConfig, $oldPort)) {
            Log::warning('Timed out waiting for OVH rule deletion before add', [
                'ip' => $this->getIpConfigValue($ipConfig, 'panel_ip'),
                'old_port' => $oldPort,
                'new_port' => $allocation->port,
            ]);
        }

        return $this->addOrUpdateRule($ipConfig, (int) $allocation->port);
    }

    /**
     * Add a firewall rule.
     */
    protected function addRule(object $ipConfig, int $port): bool
    {
        $log = OvhFirewallSyncLog::create([
            'ip' => $this->getIpConfigValue($ipConfig, 'panel_ip'),
            'ip_on_game' => $this->getIpConfigValue($ipConfig, 'ovh_ip_on_game'),
            'action' => 'add',
            'status' => 'pending',
            'port' => $port,
            'protocol' => $this->settings->default_protocol,
        ]);

        try {
            $result = $this->ovhApi->createRule(
                $this->getIpConfigValue($ipConfig, 'ovh_ip'),
                $this->getIpConfigValue($ipConfig, 'ovh_ip_on_game'),
                $port,
                $this->settings->default_protocol
            );

            $log->markAsSuccessful("Firewall rule added for port {$port}");
            $log->update([
                'rule_id' => $result['id'] ?? null,
                'details' => $result,
            ]);

            return true;
        } catch (\Exception $e) {
            $log->markAsFailed($e->getMessage(), "Failed to add firewall rule for port {$port}");
            
            return false;
        }
    }

    /**
     * Remove a firewall rule.
     */
    protected function removeRule(object $ipConfig, int $ruleId, int $port): bool
    {
        $log = OvhFirewallSyncLog::create([
            'ip' => $this->getIpConfigValue($ipConfig, 'panel_ip'),
            'ip_on_game' => $this->getIpConfigValue($ipConfig, 'ovh_ip_on_game'),
            'action' => 'delete',
            'status' => 'pending',
            'port' => $port,
            'rule_id' => $ruleId,
        ]);

        try {
            $this->ovhApi->deleteRule(
                $this->getIpConfigValue($ipConfig, 'ovh_ip'),
                $this->getIpConfigValue($ipConfig, 'ovh_ip_on_game'),
                $ruleId
            );

            $log->markAsSuccessful("Firewall rule removed for port {$port}");

            return true;
        } catch (\Exception $e) {
            $log->markAsFailed($e->getMessage(), "Failed to remove firewall rule for port {$port}");
            
            return false;
        }
    }

    /**
     * Add or update a firewall rule.
     */
    protected function addOrUpdateRule(object $ipConfig, int $port): bool
    {
        try {
            // Check if rule already exists
            $existingRule = $this->ovhApi->findRuleByPort(
                $this->getIpConfigValue($ipConfig, 'ovh_ip'),
                $this->getIpConfigValue($ipConfig, 'ovh_ip_on_game'),
                $port
            );

            if ($existingRule) {
                if ($this->shouldLogDetailed()) {
                    Log::info('Firewall rule already exists', [
                        'ip' => $this->getIpConfigValue($ipConfig, 'panel_ip'),
                        'port' => $port,
                        'rule_id' => $existingRule['id'],
                    ]);
                }
                
                return true;
            }

            // Add new rule
            return $this->addRule($ipConfig, $port);
        } catch (\Exception $e) {
            Log::error('Failed to add or update rule', [
                'ip' => $this->getIpConfigValue($ipConfig, 'panel_ip'),
                'port' => $port,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Remove a firewall rule if it exists.
     */
    protected function removeRuleIfExists(object $ipConfig, int $port): bool
    {
        try {
            $existingRule = $this->ovhApi->findRuleByPort(
                $this->getIpConfigValue($ipConfig, 'ovh_ip'),
                $this->getIpConfigValue($ipConfig, 'ovh_ip_on_game'),
                $port
            );

            if (!$existingRule) {
                if ($this->shouldLogDetailed()) {
                    Log::info('No firewall rule to remove', [
                        'ip' => $this->getIpConfigValue($ipConfig, 'panel_ip'),
                        'port' => $port,
                    ]);
                }
                
                return true;
            }

            return $this->removeRule($ipConfig, $existingRule['id'], $port);
        } catch (\Exception $e) {
            Log::error('Failed to remove rule', [
                'ip' => $this->getIpConfigValue($ipConfig, 'panel_ip'),
                'port' => $port,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get all ports from panel allocations for a specific IP.
     */
    protected function getPanelPorts(string $ip): Collection
    {
        return Allocation::where('ip', $ip)
            ->pluck('port')
            ->map(fn ($port) => (int) $port)
            ->filter(fn (int $port) => PortRangeHelper::isValidPort($port))
            ->unique()
            ->values();
    }

    /**
     * Extract ports from OVH firewall rules.
     */
    protected function extractPortsFromRules(array $rules): Collection
    {
        $ports = [];

        foreach ($rules as $rule) {
            if (isset($rule['ports'])) {
                $port = PortRangeHelper::getSinglePort($rule['ports']);
                if ($port !== null) {
                    $ports[] = $port;
                }
            }
        }

        return collect($ports);
    }

    /**
     * Find a rule by port in the rules array.
     */
    protected function findRuleByPort(array $rules, int $port): ?array
    {
        foreach ($rules as $rule) {
            if (isset($rule['ports'])) {
                $rulePort = PortRangeHelper::getSinglePort($rule['ports']);
                if ($rulePort === $port) {
                    return $rule;
                }
            }
        }

        return null;
    }

    protected function shouldLogDetailed(): bool
    {
        return (bool) config('firewall-ovh-games.logging.enabled', false);
    }

    protected function getConfiguredPanelIps(): Collection
    {
        if (class_exists(OvhFirewallIpConfig::class)) {
            return OvhFirewallIpConfig::query()
                ->where('enabled', true)
                ->pluck('panel_ip');
        }

        return DB::table('ovh_firewall_ip_configs')
            ->where('enabled', true)
            ->pluck('panel_ip');
    }

    protected function resolveIpConfig(string $ip): ?object
    {
        if (class_exists(OvhFirewallIpConfig::class)) {
            return OvhFirewallIpConfig::getForPanelIp($ip);
        }

        Log::warning('OvhFirewallIpConfig model not autoloaded, using DB fallback', ['ip' => $ip]);

        return DB::table('ovh_firewall_ip_configs')
            ->where('panel_ip', $ip)
            ->where('enabled', true)
            ->first();
    }

    protected function isIpConfigReady(object $ipConfig): bool
    {
        if (method_exists($ipConfig, 'isReadyForSync')) {
            return $ipConfig->isReadyForSync();
        }

        return !empty($this->getIpConfigValue($ipConfig, 'ovh_ip'))
            && !empty($this->getIpConfigValue($ipConfig, 'ovh_ip_on_game'));
    }

    protected function markIpConfigSynced(object $ipConfig): void
    {
        if (method_exists($ipConfig, 'updateLastSynced')) {
            $ipConfig->updateLastSynced();
            return;
        }

        $id = $this->getIpConfigValue($ipConfig, 'id');
        if ($id) {
            DB::table('ovh_firewall_ip_configs')
                ->where('id', $id)
                ->update([
                    'last_synced_at' => now(),
                    'updated_at' => now(),
                ]);
        }
    }

    protected function getIpConfigValue(object $ipConfig, string $key): mixed
    {
        return $ipConfig->{$key} ?? null;
    }

    protected function waitForRuleDeletion(object $ipConfig, int $port, int $maxAttempts = 12, int $delayMs = 500): bool
    {
        $ovhIp = (string) $this->getIpConfigValue($ipConfig, 'ovh_ip');
        $ovhIpOnGame = (string) $this->getIpConfigValue($ipConfig, 'ovh_ip_on_game');

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $exists = $this->ovhApi->ruleExistsForPort($ovhIp, $ovhIpOnGame, $port);
            if (!$exists) {
                return true;
            }

            usleep($delayMs * 1000);
        }

        return false;
    }
}
