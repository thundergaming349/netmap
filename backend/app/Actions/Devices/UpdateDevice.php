<?php

declare(strict_types=1);

namespace App\Actions\Devices;

use App\Actions\IpMap\SuggestNextFreeIp;
use App\Enums\DeviceType;
use App\Exceptions\IpAlreadyUsedException;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\Segment;
use App\Models\User;

final class UpdateDevice
{
    public function __construct(
        private readonly SuggestNextFreeIp $suggestNextFreeIp,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Device $device, array $data, ?User $actor = null): Device
    {
        $segmentId = isset($data['segment_id']) ? (int) $data['segment_id'] : $device->segment_id;
        /** @var Segment $segment */
        $segment = Segment::query()->findOrFail($segmentId);
        $ip = isset($data['ip']) ? (string) $data['ip'] : $device->ip;

        $existing = Device::query()
            ->where('segment_id', $segment->id)
            ->where('ip', $ip)
            ->where('id', '!=', $device->id)
            ->first();

        if ($existing !== null) {
            $suggestion = $this->suggestNextFreeIp->handle($segment);
            throw new IpAlreadyUsedException(
                "IP {$ip} sudah dipakai {$existing->name}.",
                $suggestion,
            );
        }

        $before = [
            'name' => $device->name,
            'ip' => $device->ip,
            'mac' => $device->mac,
            'segment_id' => $device->segment_id,
            'location' => $device->location,
            'port' => $device->port,
        ];

        $device->update([
            'segment_id' => $segment->id,
            'network_switch_id' => array_key_exists('network_switch_id', $data)
                ? ($data['network_switch_id'] !== null ? (int) $data['network_switch_id'] : null)
                : $device->network_switch_id,
            'name' => isset($data['name']) ? (string) $data['name'] : $device->name,
            'type' => isset($data['type']) ? DeviceType::from((string) $data['type']) : $device->type,
            'ip' => $ip,
            'mac' => array_key_exists('mac', $data)
                ? ($data['mac'] !== null && $data['mac'] !== '' ? strtolower((string) $data['mac']) : null)
                : $device->mac,
            'location' => array_key_exists('location', $data) ? $data['location'] : $device->location,
            'port' => array_key_exists('port', $data) ? $data['port'] : $device->port,
            'note' => array_key_exists('note', $data) ? $data['note'] : $device->note,
            'monitor_ping' => array_key_exists('monitor_ping', $data) ? (bool) $data['monitor_ping'] : $device->monitor_ping,
            'is_static_lease' => array_key_exists('is_static_lease', $data) ? (bool) $data['is_static_lease'] : $device->is_static_lease,
            'is_arp_pinned' => array_key_exists('is_arp_pinned', $data) ? (bool) $data['is_arp_pinned'] : $device->is_arp_pinned,
        ]);

        AuditLog::record(
            action: 'device.updated',
            user: $actor,
            target: "{$device->name} ({$device->ip})",
            before: $before,
            after: [
                'name' => $device->name,
                'ip' => $device->ip,
                'mac' => $device->mac,
                'segment_id' => $device->segment_id,
                'location' => $device->location,
                'port' => $device->port,
            ],
        );

        return $device->load(['segment', 'networkSwitch', 'camera']);
    }
}
