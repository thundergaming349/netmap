<?php

declare(strict_types=1);

namespace App\Actions\Devices;

use App\Enums\DeviceType;
use App\Models\Device;
use App\Models\NetworkSwitch;
use App\Models\Nvr;
use App\Models\Router;
use App\Models\Uplink;

final class TraceDevicePath
{
    /**
     * Build ordered hop-by-hop trace from device → access switch → core switch → router → NVR/ISP.
     *
     * @return array{
     *   device: array<string, mixed>,
     *   segment: array<string, mixed>,
     *   hops: list<array<string, mixed>>
     * }
     */
    public function handle(Device $device): array
    {
        $device->load(['segment.router', 'networkSwitch', 'camera.nvr']);
        $segment = $device->segment;
        $router = ($segment !== null ? $segment->router : null) ?? Router::query()->first();
        $switch = $device->networkSwitch;

        $hops = [];
        $step = 1;

        // Hop 1: The end device itself
        $hops[] = [
            'step' => $step++,
            'kind' => 'device',
            'name' => $device->name,
            'subtitle' => $device->type->label().($device->location ? " · {$device->location}" : ''),
            'ip' => $device->ip,
            'mac' => $device->mac,
            'port' => $device->port ?? 'eth0',
            'status' => $device->status->value,
            'latency_ms' => $device->latency_ms ?? 2,
        ];

        // Hop 2: Access Switch (if connected to one)
        if ($switch !== null) {
            $hops[] = [
                'step' => $step++,
                'kind' => 'switch',
                'name' => $switch->name,
                'subtitle' => ($switch->is_managed ? 'Managed Switch' : 'Switch').($switch->location ? " · {$switch->location}" : ''),
                'ip' => $switch->ip,
                'mac' => $switch->mac,
                'port' => $device->port ? "Masuk {$device->port} → Uplink ".($switch->uplink_port ?? 'port 24') : ($switch->uplink_port ?? 'uplink'),
                'status' => 'online',
                'latency_ms' => 1,
            ];

            // If on a leaf CCTV switch, include SW-CCTV-Core aggregation hop when present
            if ($segment !== null && $segment->vlan_id === 20 && $switch->name !== 'SW-CCTV-Core') {
                $coreSwitch = NetworkSwitch::query()
                    ->where('segment_id', $segment->id)
                    ->where('name', 'SW-CCTV-Core')
                    ->first();

                if ($coreSwitch !== null) {
                    $hops[] = [
                        'step' => $step++,
                        'kind' => 'switch',
                        'name' => $coreSwitch->name,
                        'subtitle' => 'Switch Distribusi CCTV · Ruang Server',
                        'ip' => $coreSwitch->ip,
                        'mac' => $coreSwitch->mac,
                        'port' => "Trunk VLAN 20 → {$coreSwitch->uplink_port}",
                        'status' => 'online',
                        'latency_ms' => 1,
                    ];
                }
            }
        }

        // Hop 3: MikroTik Router Gateway
        if ($segment !== null) {
            $hops[] = [
                'step' => $step++,
                'kind' => 'router',
                'name' => $router !== null ? $router->name : 'MikroTik-Core',
                'subtitle' => "Gateway Jalur {$segment->name}".($segment->vlan_id ? " (VLAN {$segment->vlan_id})" : ''),
                'ip' => $segment->gateway,
                'mac' => null,
                'port' => $segment->interface,
                'status' => 'online',
                'latency_ms' => 1,
            ];
        }

        // Hop 4: If camera, show NVR recording destination
        if ($device->type === DeviceType::Camera) {
            $camera = $device->camera;
            $nvr = ($camera !== null ? $camera->nvr : null) ?? Nvr::query()->first();
            if ($nvr !== null) {
                $hops[] = [
                    'step' => $step++,
                    'kind' => 'nvr',
                    'name' => $nvr->name,
                    'subtitle' => 'Perekam NVR Utama · Ruang Server',
                    'ip' => $nvr->ip,
                    'mac' => null,
                    'port' => 'Channel '.($camera !== null ? $camera->channel : 1),
                    'status' => $nvr->status,
                    'latency_ms' => 2,
                ];
            }
        }

        // Hop 5: Egress / ISP Uplink
        if ($segment !== null && $segment->egress !== null && ! str_contains(strtolower($segment->egress), 'lokal')) {
            $uplink = Uplink::query()
                ->get()
                ->first(fn (Uplink $u) => str_contains($segment->egress ?? '', $u->name))
                ?? Uplink::query()->orderBy('distance')->first();

            if ($uplink !== null) {
                $hops[] = [
                    'step' => $step++,
                    'kind' => 'uplink',
                    'name' => $uplink->name,
                    'subtitle' => ($uplink->provider ?? 'Uplink Internet')." · {$segment->egress}",
                    'ip' => $uplink->gateway,
                    'mac' => null,
                    'port' => $uplink->interface,
                    'status' => $uplink->status === 'up' ? 'online' : ($uplink->status === 'standby' ? 'risk' : 'offline'),
                    'latency_ms' => $uplink->latency_ms ?? 14,
                ];
            }
        }

        return [
            'device' => [
                'id' => $device->id,
                'name' => $device->name,
                'type' => $device->type->value,
                'type_label' => $device->type->label(),
                'ip' => $device->ip,
                'mac' => $device->mac,
                'status' => $device->status->value,
                'location' => $device->location,
                'port' => $device->port,
            ],
            'segment' => [
                'id' => $segment?->id,
                'name' => $segment?->name,
                'vlan_id' => $segment?->vlan_id,
                'subnet' => $segment?->subnet,
                'gateway' => $segment?->gateway,
                'interface' => $segment?->interface,
                'egress' => $segment?->egress,
                'color' => $segment?->color,
            ],
            'hops' => $hops,
        ];
    }
}
