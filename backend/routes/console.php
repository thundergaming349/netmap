<?php

declare(strict_types=1);

use App\Jobs\PingDevicesJob;
use App\Jobs\RecordCctvSegmentsJob;
use App\Jobs\ScanSegmentJob;
use App\Jobs\SyncMikrotikJob;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new SyncMikrotikJob)->everyThirtySeconds();
Schedule::job(new PingDevicesJob)->everyThirtySeconds();
Schedule::job(new RecordCctvSegmentsJob)->everyMinute();
Schedule::job(new ScanSegmentJob)->everyTenMinutes();
