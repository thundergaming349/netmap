<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Mikrotik\Collector;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class SyncMikrotikJob implements ShouldQueue
{
    use Queueable;

    public function handle(Collector $collector): void
    {
        $collector->sync();
    }
}
