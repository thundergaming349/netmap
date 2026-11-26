<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Device;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class PingDevicesJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Device::query()
            ->where('monitor_ping', true)
            ->where('status', '!=', 'offline')
            ->update(['last_seen_at' => now()]);
    }
}
