<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\CameraStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ConflictEventResource;
use App\Http\Resources\V1\SegmentResource;
use App\Models\AuditLog;
use App\Models\Camera;
use App\Models\ConflictEvent;
use App\Models\Device;
use App\Models\DhcpLease;
use App\Models\Router;
use App\Models\Segment;
use App\Models\Uplink;
use Illuminate\Http\JsonResponse;

final class DashboardController extends Controller
{
    public function summary(): JsonResponse
    {
        $router = Router::query()->first();
        $segments = Segment::query()->orderBy('vlan_id')->get();
        $segmentResources = SegmentResource::collection($segments)->resolve();

        $totalUsedIps = 0;
        foreach ($segmentResources as $seg) {
            $totalUsedIps += (int) ($seg['used_ips'] ?? 0);
        }
        $totalUsableIps = max(254, $segments->count() * 254);

        $totalCameras = Camera::query()->count();
        $onlineCameras = Camera::query()->where('status', CameraStatus::Online->value)->count();
        $unstableCameras = Camera::query()->where('status', CameraStatus::Unstable->value)->count();
        $offlineCameras = Camera::query()->where('status', CameraStatus::Offline->value)->count();

        $activeConflictsCount = ConflictEvent::query()
            ->active()
            ->where('severity', 'conflict')
            ->count();
        $activeRisksCount = ConflictEvent::query()
            ->active()
            ->where('severity', 'risk')
            ->count();

        $activeWarnings = ConflictEvent::query()
            ->with(['segment', 'device'])
            ->active()
            ->orderByRaw("CASE severity WHEN 'conflict' THEN 0 WHEN 'risk' THEN 1 ELSE 2 END")
            ->orderByDesc('last_detected_at')
            ->limit(5)
            ->get();

        $uplinks = Uplink::query()->orderBy('distance')->get()->map(fn (Uplink $uplink) => [
            'id' => $uplink->id,
            'name' => $uplink->name,
            'provider' => $uplink->provider,
            'interface' => $uplink->interface,
            'gateway' => $uplink->gateway,
            'check_target' => $uplink->check_target,
            'distance' => $uplink->distance,
            'status' => $uplink->status,
            'latency_ms' => $uplink->latency_ms,
            'rx_mbps' => $uplink->rx_mbps,
            'tx_mbps' => $uplink->tx_mbps,
            'last_changed_at' => $uplink->last_changed_at?->toIso8601ZuluString(),
        ]);

        $recentActivities = AuditLog::query()
            ->with('user')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'target' => $log->target,
                'actor' => $log->user ? ($log->user->name ?? $log->user->email) : 'Sistem NetMap',
                'created_at' => $log->created_at?->toIso8601ZuluString(),
            ]);

        return response()->json([
            'data' => [
                'router' => $router === null ? null : [
                    'id' => $router->id,
                    'name' => $router->name,
                    'host' => $router->host,
                    'version' => $router->version,
                    'uptime' => $router->uptime,
                    'cpu_load' => $router->cpu_load,
                    'connected' => $router->last_error === null,
                    'last_synced_at' => $router->last_synced_at?->toIso8601ZuluString(),
                    'last_error' => $router->last_error,
                ],
                'stats' => [
                    'used_ips' => $totalUsedIps,
                    'total_usable_ips' => $totalUsableIps,
                    'static_devices' => Device::query()->count(),
                    'dhcp_leases' => DhcpLease::query()->where('is_present', true)->where('is_dynamic', true)->count(),
                    'active_segments' => $segments->count(),
                    'cameras_online' => $onlineCameras,
                    'cameras_unstable' => $unstableCameras,
                    'cameras_offline' => $offlineCameras,
                    'cameras_total' => $totalCameras,
                    'active_conflicts' => $activeConflictsCount,
                    'active_risks' => $activeRisksCount,
                    'total_active_events' => $activeConflictsCount + $activeRisksCount,
                ],
                'warnings' => ConflictEventResource::collection($activeWarnings)->resolve(),
                'segments' => $segmentResources,
                'uplinks' => $uplinks,
                'activities' => $recentActivities,
                'write_enabled' => (bool) config('netmap.mikrotik_write_enabled'),
            ],
        ]);
    }
}
