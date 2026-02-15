<?php

namespace RoyalMultiGamers\FirewallOVHGames\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class OvhFirewallSyncLog extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'ovh_firewall_sync_logs';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'ip',
        'ip_on_game',
        'action',
        'status',
        'port',
        'protocol',
        'rule_id',
        'message',
        'details',
        'error',
        'synced_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'port' => 'integer',
        'rule_id' => 'integer',
        'details' => 'array',
        'synced_at' => 'datetime',
    ];

    /**
     * Scope a query to only include successful logs.
     */
    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('status', 'success');
    }

    /**
     * Scope a query to only include failed logs.
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope a query to only include pending logs.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to filter by action.
     */
    public function scopeAction(Builder $query, string $action): Builder
    {
        return $query->where('action', $action);
    }

    /**
     * Scope a query to filter by IP.
     */
    public function scopeForIp(Builder $query, string $ip): Builder
    {
        return $query->where('ip', $ip);
    }

    /**
     * Scope a query to filter by recent logs.
     */
    public function scopeRecent(Builder $query, int $days = 7): Builder
    {
        return $query->where('created_at', '>=', Carbon::now()->subDays($days));
    }

    /**
     * Mark the log as successful.
     */
    public function markAsSuccessful(?string $message = null): void
    {
        $this->update([
            'status' => 'success',
            'message' => $message ?? 'Operation completed successfully',
            'synced_at' => now(),
            'error' => null,
        ]);
    }

    /**
     * Mark the log as failed.
     */
    public function markAsFailed(string $error, ?string $message = null): void
    {
        $this->update([
            'status' => 'failed',
            'message' => $message ?? 'Operation failed',
            'error' => $error,
            'synced_at' => now(),
        ]);
    }

    /**
     * Get the status badge color.
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'success' => 'success',
            'failed' => 'danger',
            'pending' => 'warning',
            default => 'gray',
        };
    }

    /**
     * Get the action badge color.
     */
    public function getActionColorAttribute(): string
    {
        return match ($this->action) {
            'add' => 'success',
            'update' => 'info',
            'delete' => 'danger',
            'sync' => 'primary',
            default => 'gray',
        };
    }

    /**
     * Clean up old logs based on retention policy.
     */
    public static function cleanupOldLogs(): int
    {
        $retentionDays = config('firewall-ovh-games.logging.retention_days', 30);
        
        return self::where('created_at', '<', Carbon::now()->subDays($retentionDays))
            ->delete();
    }
}
