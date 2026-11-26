<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Cctv\CameraRecordingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class RecordCctvSegmentsJob implements ShouldQueue
{
    use Queueable;

    public function handle(CameraRecordingService $recordingService): void
    {
        $recordingService->runBackgroundRecordingTick();
    }
}
