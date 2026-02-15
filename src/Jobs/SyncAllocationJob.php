<?php

namespace RoyalMultiGamers\FirewallOVHGames\Jobs;

use App\Models\Allocation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RoyalMultiGamers\FirewallOVHGames\Services\FirewallSyncService;

class SyncAllocationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public Allocation $allocation, public ?int $oldPort = null)
    {
    }

    public function handle(FirewallSyncService $syncService): void
    {
        if ($this->oldPort !== null) {
            $syncService->syncAllocationPortChange($this->allocation, $this->oldPort);
            return;
        }

        $syncService->syncAllocation($this->allocation);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Failed to sync allocation via job', [
            'allocation_id' => $this->allocation->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
