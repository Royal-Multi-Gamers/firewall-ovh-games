<?php

namespace RoyalMultiGamers\FirewallOVHGames\Models;

use Illuminate\Database\Eloquent\Model;

class OvhFirewallSetting extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'ovh_firewall_settings';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'application_key',
        'application_secret',
        'endpoint',
        'consumer_key',
        'sync_enabled',
        'sync_interval',
        'sync_on_events',
        'default_protocol',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'sync_enabled' => 'boolean',
        'sync_on_events' => 'boolean',
        'sync_interval' => 'integer',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'application_secret',
        'consumer_key',
    ];

    /**
     * Get the singleton instance of settings.
     */
    public static function getInstance(): self
    {
        try {
            $settings = self::first();

            if (!$settings) {
                $settings = self::create([
                    'endpoint' => config('firewall-ovh-games.ovh.endpoint', 'ovh-eu'),
                    'sync_enabled' => config('firewall-ovh-games.sync.enabled', true),
                    'sync_interval' => config('firewall-ovh-games.sync.interval', 5),
                    'sync_on_events' => config('firewall-ovh-games.sync.sync_on_events', true),
                    'default_protocol' => config('firewall-ovh-games.rules.default_protocol', 'other'),
                ]);
            }

            return $settings;
        } catch (\Exception $e) {
            // Return a new instance with defaults if table doesn't exist
            $instance = new static();
            $instance->endpoint = 'ovh-eu';
            $instance->sync_enabled = true;
            $instance->sync_interval = 5;
            $instance->sync_on_events = true;
            $instance->default_protocol = 'other';
            return $instance;
        }
    }

    /**
     * Check if OVH API credentials are configured.
     */
    public function hasCredentials(): bool
    {
        return !empty($this->application_key)
            && !empty($this->application_secret)
            && !empty($this->consumer_key);
    }

    /**
     * Get available OVH endpoints.
     */
    public static function getAvailableEndpoints(): array
    {
        return [
            'ovh-eu' => 'Europe (ovh-eu)',
            'ovh-ca' => 'Canada (ovh-ca)',
            'ovh-us' => 'United States (ovh-us)',
            'soyoustart-eu' => 'So you Start Europe (soyoustart-eu)',
            'soyoustart-ca' => 'So you Start Canada (soyoustart-ca)',
            'kimsufi-eu' => 'Kimsufi Europe (kimsufi-eu)',
            'kimsufi-ca' => 'Kimsufi Canada (kimsufi-ca)',
        ];
    }

    /**
     * Get available protocols for firewall rules.
     */
    public static function getAvailableProtocols(): array
    {
        return [
            'arkSurvivalEvolved' => 'ARK: Survival Evolved',
            'arma' => 'ARMA',
            'gtaMultiTheftAutoSanAndreas' => 'GTA: Multi Theft Auto San Andreas',
            'gtaSanAndreasMultiplayerMod' => 'GTA: San Andreas Multiplayer Mod',
            'hl2Source' => 'Half-Life 2 Source',
            'minecraftPocketEdition' => 'Minecraft Pocket Edition',
            'minecraftQuery' => 'Minecraft Query',
            'mumble' => 'Mumble',
            'other' => 'Other',
            'rust' => 'Rust',
            'teamspeak2' => 'TeamSpeak 2',
            'teamspeak3' => 'TeamSpeak 3',
            'trackmaniaShootmania' => 'Trackmania Shootmania',
        ];
    }
}
