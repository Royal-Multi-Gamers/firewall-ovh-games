<?php

namespace RoyalMultiGamers\FirewallOVHGames\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RoyalMultiGamers\FirewallOVHGames\Services\FirewallSyncService;

class SyncFirewallRulesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(FirewallSyncService $syncService): void
    {
        $syncService->syncAllAllocations();
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Failed to run full firewall sync job', [
            'error' => $exception->getMessage(),
        ]);
    }
}
