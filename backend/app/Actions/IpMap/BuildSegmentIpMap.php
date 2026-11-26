<?php

declare(strict_types=1);

namespace App\Actions\IpMap;

use App\Enums\DeviceStatus;
use App\Enums\IpStatus;
use App\Models\ArpObservation;
use App\Models\ConflictEvent;
use App\Models\Device;
use App\Models\DhcpLease;
use App\Models\IpReservation;
use App\Models\Segment;

final class BuildSegmentIpMap
{
    public function __construct(
        private readonly SuggestNextFreeIp $suggestNextFreeIp,
    ) {}

    /**
     * @return array{
     *   segment: array<string, mixed>,
     *   summary: array<string, int|string|null>,
     *   cells: list<array<string, mixed>>
     * }
     */
    public function handle(Segment $segment): array
    {
        $devices = Device::query()
            ->with('networkSwitch')
            ->where('segment_id', $segment->id)
            ->get()
            ->keyBy('ip');

        $leases = DhcpLease::query()
            ->where('segment_id', $segment->id)
            ->where('is_present', true)
            ->get()
            ->keyBy('ip');

        $arpByIp = ArpObservation::query()
            ->where('segment_id', $segment->id)
            ->recent(2)
            ->orderByDesc('last_seen_at')
            ->get()
            ->groupBy('ip');

        $activeConflicts = ConflictEvent::query()
            ->active()
            ->where('segment_id', $segment->id)
            ->get()
            ->keyBy('ip');

        $reservations = IpReservation::query()
            ->where('segment_id', $segment->id)
            ->orderBy('start_octet')
            ->get();

        $gatewayOctet = $segment->octetOf($segment->gateway) ?? 1;
        $counts = [
            'gateway' => 0,
            'static' => 0,
            'dhcp' => 0,
            'reserved' => 0,
            'conflict' => 0,
            'risk' => 0,
            'offline' => 0,
            'free' => 0,
            'network' => 0,
        ];

        $cells = [];

        for ($octet = 0; $octet <= 255; $octet++) {
            $ip = $segment->ipForOctet($octet);
            $device = $devices->get($ip);
            $lease = $leases->get($ip);
            $arpList = $arpByIp->get($ip);
            $latestArp = $arpList?->first();
            $conflict = $activeConflicts->get($ip);
            $reservation = $reservations->first(fn (IpReservation $res) => $res->containsOctet($octet));
            $inPool = $segment->isInPool($ip);

            if ($octet === 0 || $octet === 255) {
                $status = IpStatus::Network;
            } elseif ($octet === $gatewayOctet) {
                $status = IpStatus::Gateway;
            } elseif ($conflict !== null && $conflict->severity->value === 'conflict') {
                $status = IpStatus::Conflict;
            } elseif ($conflict !== null && $conflict->severity->value === 'risk') {
                $status = IpStatus::Risk;
            } elseif ($device !== null) {
                if ($device->status === DeviceStatus::Conflict) {
                    $status = IpStatus::Conflict;
                } elseif ($device->status === DeviceStatus::Offline) {
                    $status = IpStatus::Offline;
                } elseif ($inPool && ! $device->is_static_lease) {
                    $status = IpStatus::Risk;
                } else {
                    $status = IpStatus::Static;
                }
            } elseif ($lease !== null) {
                $status = $lease->is_dynamic ? IpStatus::Dhcp : IpStatus::Static;
            } elseif ($reservation !== null) {
                $status = IpStatus::Reserved;
            } elseif ($latestArp !== null) {
                $status = $inPool ? IpStatus::Dhcp : IpStatus::Static;
            } else {
                $status = IpStatus::Free;
            }

            $counts[$status->value]++;

            $portDisplay = null;
            if ($device !== null) {
                $portDisplay = $device->networkSwitch !== null && $device->port !== null
                    ? "{$device->networkSwitch->name} {$device->port}"
                    : $device->port;
            }

            $cells[] = [
                'octet' => $octet,
                'ip' => $ip,
                'status' => $status->value,
                'status_label' => $status->label(),
                'in_pool' => $inPool,
                'device' => $device === null ? null : [
                    'id' => $device->id,
                    'name' => $device->name,
                    'type' => $device->type->value,
                    'type_label' => $device->type->label(),
                    'status' => $device->status->value,
                    'location' => $device->location,
                    'port' => $portDisplay,
                    'is_static_lease' => $device->is_static_lease,
                    'is_arp_pinned' => $device->is_arp_pinned,
                    'latency_ms' => $device->latency_ms,
                ],
                'lease' => $lease === null ? null : [
                    'host_name' => $lease->host_name,
                    'is_dynamic' => $lease->is_dynamic,
                    'server' => $lease->server,
                ],
                'reservation' => $reservation === null ? null : [
                    'id' => $reservation->id,
                    'label' => $reservation->label,
                    'range' => ".{$reservation->start_octet}–.{$reservation->end_octet}",
                    'description' => $reservation->description,
                ],
                'conflict' => $conflict === null ? null : [
                    'id' => $conflict->id,
                    'type' => $conflict->type->value,
                    'severity' => $conflict->severity->value,
                    'title' => $conflict->title,
                    'summary' => $conflict->summary,
                    'suggestion' => $conflict->suggestion,
                    'macs' => $conflict->macs,
                ],
                'mac' => ($device !== null ? $device->mac : null)
                    ?? ($lease !== null ? $lease->mac : null)
                    ?? ($latestArp !== null ? $latestArp->mac : null),
                'last_seen_at' => (
                    ($device !== null ? $device->last_seen_at : null)
                    ?? ($lease !== null ? $lease->last_seen_at : null)
                    ?? ($latestArp !== null ? $latestArp->last_seen_at : null)
                )?->toIso8601ZuluString(),
                'note' => ($conflict !== null ? $conflict->summary : null)
                    ?? ($device !== null ? $device->note : null)
                    ?? ($reservation !== null ? $reservation->description : null),
            ];
        }

        $usedCount = $counts['gateway'] + $counts['static'] + $counts['dhcp'] + $counts['conflict'] + $counts['risk'] + $counts['offline'];

        return [
            'segment' => [
                'id' => $segment->id,
                'name' => $segment->name,
                'slug' => $segment->slug,
                'vlan_id' => $segment->vlan_id,
                'subnet' => $segment->subnet,
                'gateway' => $segment->gateway,
                'interface' => $segment->interface,
                'pool_start' => $segment->pool_start,
                'pool_end' => $segment->pool_end,
                'static_start' => $segment->static_start,
                'static_end' => $segment->static_end,
                'egress' => $segment->egress,
                'color' => $segment->color,
                'is_isolated' => $segment->is_isolated,
                'bandwidth_limit' => $segment->bandwidth_limit,
            ],
            'summary' => [
                'used_count' => $usedCount,
                'usable_total' => 254,
                'free_count' => $counts['free'],
                'reserved_count' => $counts['reserved'],
                'conflict_count' => $counts['conflict'],
                'risk_count' => $counts['risk'],
                'offline_count' => $counts['offline'],
                'static_count' => $counts['static'],
                'dhcp_count' => $counts['dhcp'],
                'next_free_ip' => $this->suggestNextFreeIp->handle($segment),
            ],
            'cells' => $cells,
        ];
    }
}
