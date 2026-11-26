<?php

declare(strict_types=1);

namespace App\Actions\Mikrotik;

use App\Enums\CameraStatus;
use App\Enums\DeviceStatus;
use App\Models\AuditLog;
use App\Models\Camera;
use App\Models\Device;
use App\Models\User;
use App\Services\Mikrotik\MikrotikClient;

final class RestartPoePort
{
    public function __construct(
        private readonly MikrotikClient $client,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(string $interface, ?User $actor = null): array
    {
        $result = $this->client->restartPoe($interface);

        // If an offline camera/device is attached to this port, bring it back online after PoE cycle.
        $device = Device::query()->where('port', $interface)->first();
        if ($device !== null) {
            $device->update([
                'status' => DeviceStatus::Online,
                'last_seen_at' => now(),
                'latency_ms' => 5,
            ]);
            Camera::query()->where('device_id', $device->id)->update([
                'status' => CameraStatus::Online,
                'last_ok_at' => now(),
            ]);
        }

        AuditLog::record(
            action: 'mikrotik.poe.restart',
            user: $actor,
            target: $interface,
            after: $result,
        );

        return $result;
    }
}
