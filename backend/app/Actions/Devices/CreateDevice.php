<?php

declare(strict_types=1);

namespace App\Actions\Devices;

use App\Actions\IpMap\SuggestNextFreeIp;
use App\Enums\CameraStatus;
use App\Enums\DeviceStatus;
use App\Enums\DeviceType;
use App\Exceptions\IpAlreadyUsedException;
use App\Models\AuditLog;
use App\Models\Camera;
use App\Models\Device;
use App\Models\Nvr;
use App\Models\Segment;
use App\Models\User;
use Illuminate\Support\Str;

final class CreateDevice
{
    public function __construct(
        private readonly SuggestNextFreeIp $suggestNextFreeIp,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, ?User $actor = null): Device
    {
        /** @var Segment $segment */
        $segment = Segment::query()->findOrFail((int) $data['segment_id']);
        $ip = (string) $data['ip'];

        $existing = Device::query()
            ->where('segment_id', $segment->id)
            ->where('ip', $ip)
            ->first();

        if ($existing !== null) {
            $suggestion = $this->suggestNextFreeIp->handle($segment);
            throw new IpAlreadyUsedException(
                "IP {$ip} sudah dipakai {$existing->name}.",
                $suggestion,
            );
        }

        $type = DeviceType::from((string) $data['type']);

        $device = Device::create([
            'segment_id' => $segment->id,
            'network_switch_id' => isset($data['network_switch_id']) ? (int) $data['network_switch_id'] : null,
            'name' => (string) $data['name'],
            'type' => $type,
            'status' => DeviceStatus::Online,
            'ip' => $ip,
            'mac' => isset($data['mac']) && $data['mac'] !== '' ? strtolower((string) $data['mac']) : null,
            'location' => $data['location'] ?? null,
            'port' => $data['port'] ?? null,
            'note' => $data['note'] ?? null,
            'monitor_ping' => (bool) ($data['monitor_ping'] ?? true),
            'is_static_lease' => (bool) ($data['is_static_lease'] ?? false),
            'is_arp_pinned' => (bool) ($data['is_arp_pinned'] ?? false),
            'last_seen_at' => now(),
            'latency_ms' => 4,
        ]);

        if ($type === DeviceType::Camera) {
            $nvr = Nvr::query()->first();
            $streamKey = 'cam_'.Str::slug($device->name, '_');
            Camera::create([
                'device_id' => $device->id,
                'nvr_id' => isset($data['nvr_id']) ? (int) $data['nvr_id'] : $nvr?->id,
                'channel' => (int) ($data['channel'] ?? (Camera::query()->max('channel') + 1)),
                'brand' => (string) ($data['brand'] ?? 'hikvision'),
                'location_group' => (string) ($data['location'] ?? 'Gedung A'),
                'stream_key' => $streamKey,
                'stream_main' => "rtsp://{$device->ip}:554/Streaming/Channels/101",
                'stream_sub' => "rtsp://{$device->ip}:554/Streaming/Channels/102",
                'status' => CameraStatus::Online,
                'fps' => 25,
                'resolution' => '1920x1080',
                'last_ok_at' => now(),
            ]);
        }

        AuditLog::record(
            action: 'device.created',
            user: $actor,
            target: "{$device->name} ({$device->ip})",
            after: [
                'name' => $device->name,
                'type' => $device->type->value,
                'segment_id' => $device->segment_id,
                'ip' => $device->ip,
                'mac' => $device->mac,
            ],
        );

        return $device->load(['segment', 'networkSwitch', 'camera']);
    }
}
