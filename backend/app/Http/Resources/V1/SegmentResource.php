<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Actions\IpMap\SuggestNextFreeIp;
use App\Models\ArpObservation;
use App\Models\ConflictEvent;
use App\Models\Device;
use App\Models\DhcpLease;
use App\Models\Segment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Segment
 */
final class SegmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $deviceCount = Device::query()->where('segment_id', $this->id)->count();
        $dynamicLeaseCount = DhcpLease::query()
            ->where('segment_id', $this->id)
            ->where('is_present', true)
            ->where('is_dynamic', true)
            ->count();
        $activeConflicts = ConflictEvent::query()
            ->active()
            ->where('segment_id', $this->id)
            ->count();

        // Include gateway (.1) in total used IPs
        $usedIps = min(254, 1 + $deviceCount + $dynamicLeaseCount);
        $nextFreeIp = app(SuggestNextFreeIp::class)->handle($this->resource);

        return [
            'id' => $this->id,
            'router_id' => $this->router_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'vlan_id' => $this->vlan_id,
            'subnet' => $this->subnet,
            'gateway' => $this->gateway,
            'interface' => $this->interface,
            'pool_start' => $this->pool_start,
            'pool_end' => $this->pool_end,
            'static_start' => $this->static_start,
            'static_end' => $this->static_end,
            'egress' => $this->egress,
            'color' => $this->color,
            'is_isolated' => $this->is_isolated,
            'bandwidth_limit' => $this->bandwidth_limit,
            'notes' => $this->notes,
            'used_ips' => $usedIps,
            'usable_ips' => 254,
            'usage_percent' => round(($usedIps / 254) * 100, 1),
            'device_count' => $deviceCount,
            'dhcp_lease_count' => $dynamicLeaseCount,
            'active_conflict_count' => $activeConflicts,
            'arp_active_count' => ArpObservation::query()->where('segment_id', $this->id)->recent(60)->count(),
            'next_free_ip' => $nextFreeIp,
        ];
    }
}
