<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Mikrotik\Collector;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class ScanSegmentJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly ?int $segmentId = null,
    ) {}

    public function handle(Collector $collector): void
    {
        $collector->sync();
    }
}
