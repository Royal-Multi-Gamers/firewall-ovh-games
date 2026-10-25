<?php

namespace RoyalMultiGamers\FirewallOVHGames\Jobs;

use App\Models\Allocation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use RoyalMultiGamers\FirewallOVHGames\Services\FirewallSyncService;

/**
 * Stores scalar values instead of the Allocation model: with SerializesModels a
 * deleted (or never persisted) allocation cannot be restored when the job runs,
 * so rule removals were silently failing with ModelNotFoundException.
 */
class SyncAllocationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public function __construct(
        public string $ip,
        public int $port,
        public ?int $allocationId = null,
        public ?int $oldPort = null,
    ) {
    }

    public static function fromAllocation(Allocation $allocation, ?int $oldPort = null): self
    {
        return new self(
            (string) $allocation->ip,
            (int) $allocation->port,
            $allocation->exists ? (int) $allocation->id : null,
            $oldPort,
        );
    }

    public function handle(FirewallSyncService $syncService): void
    {
        $allocation = $this->allocationId !== null
            ? Allocation::find($this->allocationId)
            : null;

        // Missing allocation = it was deleted: sync with a transient instance to remove the rule.
        $allocation ??= (new Allocation())->forceFill([
            'ip' => $this->ip,
            'port' => $this->port,
            'server_id' => null,
        ]);

        if ($this->oldPort !== null) {
            $syncService->syncAllocationPortChange($allocation, $this->oldPort);
            return;
        }

        $syncService->syncAllocation($allocation);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Failed to sync allocation via job', [
            'allocation_id' => $this->allocationId,
            'ip' => $this->ip,
            'port' => $this->port,
            'error' => $exception->getMessage(),
        ]);
    }
}
