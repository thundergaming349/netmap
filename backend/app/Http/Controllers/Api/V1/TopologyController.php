<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\RouterRouteResource;
use App\Models\Device;
use App\Models\NetworkSwitch;
use App\Models\Router;
use App\Models\RouterRoute;
use App\Models\Segment;
use App\Models\Uplink;
use App\Services\Mikrotik\Collector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;

final class TopologyController extends Controller
{
    public function topology(Collector $collector): JsonResponse
    {
        if (! app()->runningUnitTests() && Cache::add('netmap_live_sync_lock', true, 8)) {
            try {
                $collector->sync();
            } catch (\Throwable) {
                // Continue rendering topology
            }
        }

        $router = Router::query()->first();
        $uplinks = Uplink::query()->orderBy('distance')->get();
        $segments = Segment::query()->with('switches')->orderBy('vlan_id')->get();

        $lanes = $segments->map(function (Segment $segment): array {
            $devices = Device::query()->where('segment_id', $segment->id)->get();
            $switches = $segment->switches->map(function (NetworkSwitch $sw) use ($devices): array {
                $attached = $devices->where('network_switch_id', $sw->id);

                return [
                    'id' => $sw->id,
                    'name' => $sw->name,
                    'ip' => $sw->ip,
                    'mac' => $sw->mac,
                    'uplink_port' => $sw->uplink_port,
                    'total_ports' => $sw->total_ports,
                    'is_managed' => $sw->is_managed,
                    'location' => $sw->location,
                    'device_count' => $attached->count(),
                    'online_count' => $attached->where('status.value', 'online')->count(),
                    'offline_count' => $attached->where('status.value', 'offline')->count(),
                ];
            })->values()->all();

            return [
                'id' => $segment->id,
                'name' => $segment->name,
                'slug' => $segment->slug,
                'vlan_id' => $segment->vlan_id,
                'subnet' => $segment->subnet,
                'gateway' => $segment->gateway,
                'interface' => $segment->interface,
                'egress' => $segment->egress,
                'color' => $segment->color,
                'is_isolated' => $segment->is_isolated,
                'bandwidth_limit' => $segment->bandwidth_limit,
                'notes' => $segment->notes,
                'switches' => $switches,
                'device_summary' => [
                    'total' => $devices->count(),
                    'online' => $devices->filter(fn (Device $d) => $d->status->value === 'online')->count(),
                    'offline' => $devices->filter(fn (Device $d) => $d->status->value === 'offline')->count(),
                    'conflict' => $devices->filter(fn (Device $d) => $d->status->value === 'conflict')->count(),
                    'by_type' => $devices->groupBy(fn (Device $d) => $d->type->label())
                        ->map(fn ($group) => $group->count())
                        ->all(),
                ],
            ];
        })->values()->all();

        $allSwitches = NetworkSwitch::query()->orderBy('name')->get(['id', 'segment_id', 'name', 'ip', 'location', 'total_ports', 'is_managed']);

        return response()->json([
            'data' => [
                'router' => $router === null ? null : [
                    'id' => $router->id,
                    'name' => $router->name,
                    'host' => $router->host,
                    'version' => $router->version,
                    'uptime' => $router->uptime,
                    'cpu_load' => $router->cpu_load,
                ],
                'uplinks' => $uplinks->map(fn (Uplink $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'provider' => $u->provider,
                    'interface' => $u->interface,
                    'gateway' => $u->gateway,
                    'check_target' => $u->check_target,
                    'distance' => $u->distance,
                    'status' => $u->status,
                    'latency_ms' => $u->latency_ms,
                    'rx_mbps' => $u->rx_mbps,
                    'tx_mbps' => $u->tx_mbps,
                ])->values()->all(),
                'lanes' => $lanes,
                'switches' => $allSwitches,
            ],
        ]);
    }

    public function routes(): AnonymousResourceCollection
    {
        $routes = RouterRoute::query()
            ->where('is_present', true)
            ->orderBy('routing_table')
            ->orderBy('distance')
            ->orderBy('dst_address')
            ->get();

        return RouterRouteResource::collection($routes);
    }
}
