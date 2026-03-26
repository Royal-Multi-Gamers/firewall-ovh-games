<?php

namespace RoyalMultiGamers\FirewallOVHGames\Observers;

use App\Models\Allocation;
use RoyalMultiGamers\FirewallOVHGames\Models\OvhFirewallSetting;
use RoyalMultiGamers\FirewallOVHGames\Services\FirewallSyncService;
use RoyalMultiGamers\FirewallOVHGames\Jobs\SyncAllocationJob;
use Illuminate\Support\Facades\Log;

class AllocationObserver
{
    protected FirewallSyncService $syncService;

    public function __construct(FirewallSyncService $syncService)
    {
        $this->syncService = $syncService;
    }

    /**
     * Handle the Allocation "created" event.
     */
    public function created(Allocation $allocation): void
    {
        if (!$this->shouldSync()) {
            return;
        }

        Log::info('Allocation created, syncing firewall', [
            'allocation_id' => $allocation->id,
            'ip' => $allocation->ip,
            'port' => $allocation->port,
            'server_id' => $allocation->server_id,
        ]);

        $this->dispatchSync($allocation);
    }

    /**
     * Handle the Allocation "updated" event.
     */
    public function updated(Allocation $allocation): void
    {
        if (!$this->shouldSync()) {
            return;
        }

        // Check if server_id or port changed (updated event runs after save)
        $serverIdChanged = $allocation->wasChanged('server_id');
        $portChanged = $allocation->wasChanged('port');

        if (!$serverIdChanged && !$portChanged) {
            return;
        }

        Log::info('Allocation updated, syncing firewall', [
            'allocation_id' => $allocation->id,
            'ip' => $allocation->ip,
            'port' => $allocation->port,
            'server_id' => $allocation->server_id,
            'server_id_changed' => $serverIdChanged,
            'port_changed' => $portChanged,
        ]);

        if ($portChanged) {
            $oldPort = (int) $allocation->getOriginal('port');
            $this->dispatchPortChangeSync($allocation, $oldPort);
            return;
        }

        $this->dispatchSync($allocation);
    }

    /**
     * Handle the Allocation "deleted" event.
     */
    public function deleted(Allocation $allocation): void
    {
        if (!$this->shouldSync()) {
            return;
        }

        Log::info('Allocation deleted, syncing firewall', [
            'allocation_id' => $allocation->id,
            'ip' => $allocation->ip,
            'port' => $allocation->port,
        ]);

        // Create a temporary allocation object with no server to trigger removal
        $tempAllocation = new Allocation([
            'ip' => $allocation->ip,
            'port' => $allocation->port,
            'server_id' => null,
        ]);

        $this->dispatchSync($tempAllocation);
    }

    /**
     * Check if sync should be performed.
     * Always reads fresh settings so worker processes pick up admin changes.
     */
    protected function shouldSync(): bool
    {
        $settings = OvhFirewallSetting::getInstance();
        return $settings->sync_enabled && $settings->sync_on_events;
    }

    /**
     * Dispatch the sync operation.
     */
    protected function dispatchSync(Allocation $allocation): void
    {
        if (config('firewall-ovh-games.sync.use_queue', false)) {
            SyncAllocationJob::dispatch($allocation)
                ->onQueue(config('firewall-ovh-games.sync.queue_name', 'default'));
        } else {
            // Run after response to avoid slowing down panel requests
            dispatch(function () use ($allocation) {
                try {
                    $this->syncService->syncAllocation($allocation);
                } catch (\Exception $e) {
                    Log::error('Failed to sync allocation', [
                        'allocation_id' => $allocation->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            })->afterResponse();
        }
    }

    protected function dispatchPortChangeSync(Allocation $allocation, int $oldPort): void
    {
        if (config('firewall-ovh-games.sync.use_queue', false)) {
            SyncAllocationJob::dispatch($allocation, $oldPort)
                ->onQueue(config('firewall-ovh-games.sync.queue_name', 'default'));
        } else {
            dispatch(function () use ($allocation, $oldPort) {
                try {
                    $this->syncService->syncAllocationPortChange($allocation, $oldPort);
                } catch (\Exception $e) {
                    Log::error('Failed to sync allocation port change', [
                        'allocation_id' => $allocation->id,
                        'old_port' => $oldPort,
                        'new_port' => $allocation->port,
                        'error' => $e->getMessage(),
                    ]);
                }
            })->afterResponse();
        }
    }
}
