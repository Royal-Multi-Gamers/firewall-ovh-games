<?php

namespace RoyalMultiGamers\FirewallOVHGames\Models;

use App\Models\Node;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OvhFirewallIpConfig extends Model
{
    protected $fillable = [
        'node_id',
        'panel_ip',
        'ovh_ip',
        'ovh_ip_on_game',
        'enabled',
        'notes',
        'last_synced_at',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    /**
     * Get the node that owns this IP configuration.
     */
    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }

    /**
     * Scope to get only enabled configurations.
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * Get configuration by panel IP.
     */
    public static function getByPanelIp(string $panelIp): ?self
    {
        return static::where('panel_ip', $panelIp)
            ->where('enabled', true)
            ->first();
    }

    /**
     * Get configuration for panel IP (alias for getByPanelIp).
     */
    public static function getForPanelIp(string $panelIp): ?self
    {
        return static::getByPanelIp($panelIp);
    }

    /**
     * Check if this configuration is ready for synchronization.
     */
    public function isReadyForSync(): bool
    {
        return $this->enabled 
            && !empty($this->ovh_ip) 
            && !empty($this->ovh_ip_on_game);
    }

    /**
     * Update last synced timestamp.
     */
    public function updateLastSynced(): void
    {
        $this->update(['last_synced_at' => now()]);
    }

    /**
     * Update last synced timestamp (alias).
     */
    public function markAsSynced(): void
    {
        $this->updateLastSynced();
    }

    /**
     * Get all enabled configurations as a map (panel_ip => config).
     */
    public static function getEnabledConfigsMap(): array
    {
        return static::enabled()
            ->get()
            ->keyBy('panel_ip')
            ->toArray();
    }

    /**
     * Get all unique panel IPs from nodes.
     */
    public static function getAvailableNodeIps(): array
    {
        $ips = [];
        $filtered = [];
        
        $nodes = Node::all();
        
        foreach ($nodes as $node) {
            $nodeIps = $node->ipAddresses();
            foreach ($nodeIps as $ip) {
                // Skip localhost and wildcard IPs
                if (in_array($ip, ['0.0.0.0', '::', '127.0.0.1', 'localhost'])) {
                    continue;
                }

                if (static::shouldIgnoreNodeIp($ip)) {
                    $filtered[$ip] = true;
                    continue;
                }

                $ips[$ip] = [
                    'ip' => $ip,
                    'node_id' => $node->id,
                    'node_name' => $node->name,
                ];
            }
        }

        return [
            'ips' => $ips,
            'filtered' => array_keys($filtered),
        ];
    }

    /**
     * Determine whether this node IP should be ignored during auto-discovery.
     */
    protected static function shouldIgnoreNodeIp(string $ip): bool
    {
        if (!(bool) config('firewall-ovh-games.discovery.ignore_docker_ips', true)) {
            return false;
        }

        // Common Docker bridge/compose ranges on hosts.
        if (preg_match('/^172\.(1[7-9]|2[0-9]|3[0-1])\./', $ip) === 1) {
            return true;
        }

        return false;
    }

    /**
     * Sync IP configurations from nodes.
     * Creates missing configurations for new node IPs.
     */
    public static function syncFromNodes(): array
    {
        $discovery = static::getAvailableNodeIps();
        $nodeIps = $discovery['ips'];
        $filteredIps = $discovery['filtered'];
        $existingConfigs = static::pluck('panel_ip')->toArray();
        
        $created = [];
        $skipped = [];
        
        foreach ($nodeIps as $ipData) {
            if (!in_array($ipData['ip'], $existingConfigs)) {
                // Create new configuration (disabled by default until admin configures OVH mapping)
                $config = static::create([
                    'node_id' => $ipData['node_id'],
                    'panel_ip' => $ipData['ip'],
                    'ovh_ip' => '', // To be configured by admin
                    'ovh_ip_on_game' => '', // To be configured by admin
                    'enabled' => false,
                    'notes' => "Auto-detected from node: {$ipData['node_name']}",
                ]);
                
                $created[] = $ipData['ip'];
            } else {
                $skipped[] = $ipData['ip'];
            }
        }
        
        return [
            'created' => $created,
            'skipped' => $skipped,
            'total_node_ips' => count($nodeIps),
            'filtered' => $filteredIps,
            'filtered_count' => count($filteredIps),
        ];
    }
}
