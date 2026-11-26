<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Enums\IpStatus;
use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Device
 */
final class DeviceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $segment = $this->whenLoaded('segment');
        $switch = $this->whenLoaded('networkSwitch');

        $ipStatus = IpStatus::Static;
        if ($this->status->value === 'conflict') {
            $ipStatus = IpStatus::Conflict;
        } elseif ($this->status->value === 'offline') {
            $ipStatus = IpStatus::Offline;
        } elseif ($this->segment !== null && $this->segment->isInPool($this->ip) && ! $this->is_static_lease) {
            $ipStatus = IpStatus::Risk;
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'ip_status' => $ipStatus->value,
            'ip_status_label' => $ipStatus->label(),
            'segment_id' => $this->segment_id,
            'segment' => $segment ? [
                'id' => $this->segment?->id,
                'name' => $this->segment?->name,
                'slug' => $this->segment?->slug,
                'vlan_id' => $this->segment?->vlan_id,
                'color' => $this->segment?->color,
                'subnet' => $this->segment?->subnet,
            ] : null,
            'network_switch_id' => $this->network_switch_id,
            'switch_name' => $switch ? $this->networkSwitch?->name : null,
            'ip' => $this->ip,
            'mac' => $this->mac,
            'location' => $this->location,
            'port' => $this->port,
            'note' => $this->note,
            'monitor_ping' => $this->monitor_ping,
            'is_static_lease' => $this->is_static_lease,
            'is_arp_pinned' => $this->is_arp_pinned,
            'latency_ms' => $this->latency_ms,
            'last_seen_at' => $this->last_seen_at?->toIso8601ZuluString(),
            'created_at' => $this->created_at?->toIso8601ZuluString(),
        ];
    }
}
