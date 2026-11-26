<?php

declare(strict_types=1);

namespace App\Actions\Devices;

use App\Models\AuditLog;
use App\Models\Device;
use App\Models\User;

final class DeleteDevice
{
    public function handle(Device $device, ?User $actor = null): void
    {
        $before = [
            'id' => $device->id,
            'name' => $device->name,
            'ip' => $device->ip,
            'mac' => $device->mac,
            'segment_id' => $device->segment_id,
        ];

        $device->delete();

        AuditLog::record(
            action: 'device.deleted',
            user: $actor,
            target: "{$device->name} ({$device->ip})",
            before: $before,
        );
    }
}
