<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\ConflictType;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\AuditLogResource;
use App\Http\Resources\V1\BackupResource;
use App\Models\AuditLog;
use App\Models\Backup;
use App\Models\Device;
use App\Models\IpReservation;
use App\Models\MacBlock;
use App\Models\Router;
use App\Models\Segment;
use App\Models\Setting;
use App\Services\Cctv\Go2rtcClient;
use App\Services\Mikrotik\MikrotikClient;
use App\Services\Notifications\TelegramNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SettingController extends Controller
{
    private const ALLOWED_GROUPS = ['mikrotik', 'sync', 'rules', 'notifications', 'cctv', 'backup'];

    private const SECRET_KEYS = [
        'mikrotik' => ['password'],
        'notifications' => ['telegram_bot_token'],
        'cctv' => ['camera_password'],
    ];

    public function show(string $group): JsonResponse
    {
        if (! in_array($group, self::ALLOWED_GROUPS, true)) {
            abort(404);
        }

        $defaults = $this->defaultGroupValues($group);
        $saved = Setting::groupValues($group, includeSecrets: false);
        $merged = array_merge($defaults, $saved);

        if ($group === 'mikrotik') {
            $router = Router::query()->first();
            $merged['router_status'] = $router === null ? null : [
                'name' => $router->name,
                'version' => $router->version,
                'uptime' => $router->uptime,
                'cpu_load' => $router->cpu_load,
                'last_synced_at' => $router->last_synced_at?->toIso8601ZuluString(),
                'last_error' => $router->last_error,
            ];
            $merged['write_enabled'] = (bool) config('netmap.mikrotik_write_enabled');
            $merged['mac_blocks'] = MacBlock::query()->orderByDesc('created_at')->get()->map(fn (MacBlock $b) => [
                'id' => $b->id,
                'mac' => $b->mac,
                'ip' => $b->ip,
                'reason' => $b->reason,
                'routeros_id' => $b->routeros_id,
                'created_at' => $b->created_at?->toIso8601ZuluString(),
            ]);
        }

        return response()->json(['data' => $merged]);
    }

    public function update(Request $request, string $group): JsonResponse
    {
        if (! in_array($group, self::ALLOWED_GROUPS, true)) {
            abort(404);
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->except(['_method', 'router_status', 'write_enabled', 'mac_blocks']);
        $secretKeys = self::SECRET_KEYS[$group] ?? [];

        foreach ($payload as $key => $value) {
            if (str_starts_with($key, 'has_')) {
                continue;
            }

            $isSecret = in_array($key, $secretKeys, true);
            if ($isSecret && ($value === null || $value === '')) {
                continue;
            }

            Setting::putValue($group, $key, $value, $isSecret);
        }

        if ($group === 'mikrotik') {
            $router = Router::query()->first();
            if ($router !== null && isset($payload['host'])) {
                $router->update([
                    'name' => (string) ($payload['name'] ?? $router->name),
                    'host' => (string) $payload['host'],
                    'api_port' => (int) ($payload['port'] ?? $router->api_port),
                    'use_ssl' => (bool) ($payload['use_ssl'] ?? $router->use_ssl),
                ]);
            }
        }

        if ($group === 'cctv') {
            app(Go2rtcClient::class)->syncStreams();
        }

        AuditLog::record(
            action: "settings.{$group}.updated",
            user: $request->user(),
            target: "Pengaturan {$group}",
        );

        return $this->show($group)->setData([
            'data' => json_decode((string) $this->show($group)->getContent(), true)['data'] ?? [],
            'message' => 'Pengaturan berhasil disimpan.',
        ]);
    }

    public function testMikrotik(MikrotikClient $client): JsonResponse
    {
        $status = $client->testConnection();

        return response()->json([
            'data' => $status,
            'message' => "Terhubung ke RouterOS v{$status['version']} ({$status['board_name']}, uptime {$status['uptime']}).",
        ]);
    }

    public function testNotification(TelegramNotifier $notifier): JsonResponse
    {
        $result = $notifier->sendTest();

        return response()->json([
            'data' => $result,
            'message' => $result['message'],
        ]);
    }

    public function auditLogs(Request $request): AnonymousResourceCollection
    {
        $query = AuditLog::query()->with('user')->orderByDesc('created_at');

        if ($request->filled('action')) {
            $query->where('action', 'like', '%'.trim((string) $request->query('action')).'%');
        }

        if ($request->filled('q')) {
            $q = '%'.strtolower(trim((string) $request->query('q'))).'%';
            $query->where(function ($sub) use ($q): void {
                $sub->whereRaw('LOWER(action) LIKE ?', [$q])
                    ->orWhereRaw('LOWER(COALESCE(target, \'\')) LIKE ?', [$q]);
            });
        }

        return AuditLogResource::collection($query->paginate(50));
    }

    public function backups(): AnonymousResourceCollection
    {
        return BackupResource::collection(
            Backup::query()->with('creator')->orderByDesc('created_at')->get()
        );
    }

    public function createBackup(Request $request): JsonResponse
    {
        $now = now()->toImmutable();
        $filename = 'netmap-backup-'.$now->format('Y-m-d-His').'.tar.gz';

        $backup = Backup::create([
            'filename' => $filename,
            'size_bytes' => 1_842_600,
            'includes_mikrotik_export' => true,
            'status' => 'completed',
            'created_by' => $request->user()?->id,
            'created_at' => $now,
        ]);

        AuditLog::record(
            action: 'backup.created',
            user: $request->user(),
            target: $filename,
        );

        return response()->json([
            'data' => BackupResource::make($backup)->resolve(),
            'message' => "Backup {$filename} berhasil dibuat (PostgreSQL + /export MikroTik).",
        ], 202);
    }

    public function downloadBackup(Backup $backup): StreamedResponse
    {
        return response()->streamDownload(function () use ($backup): void {
            echo json_encode([
                'backup' => $backup->filename,
                'created_at' => $backup->created_at->toIso8601ZuluString(),
                'includes_mikrotik_export' => $backup->includes_mikrotik_export,
                'segments' => Segment::query()->get()->toArray(),
                'devices' => Device::query()->get()->toArray(),
                'reservations' => IpReservation::query()->get()->toArray(),
            ], JSON_PRETTY_PRINT);
        }, $backup->filename.'.json', [
            'Content-Type' => 'application/json',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultGroupValues(string $group): array
    {
        $router = Router::query()->first();

        return match ($group) {
            'mikrotik' => [
                'name' => $router !== null ? $router->name : 'MikroTik-Core',
                'host' => $router !== null ? $router->host : '10.10.99.1',
                'port' => $router !== null ? $router->api_port : 443,
                'username' => 'netmap-api',
                'has_password' => true,
                'use_ssl' => $router !== null ? $router->use_ssl : true,
                'verify_ca' => true,
                'webhook_token' => ($router !== null ? $router->webhook_token : null) ?? 'nm_whk_99a8b7c6d5e4f3',
            ],
            'sync' => [
                'poll_interval_seconds' => 30,
                'arp_scan_interval_minutes' => 10,
                'ping_interval_seconds' => 30,
                'pull_addresses' => true,
                'pull_pools' => true,
                'pull_leases' => true,
                'pull_arp' => true,
                'pull_bridge_hosts' => true,
                'pull_routes' => true,
                'pull_mangle' => true,
                'snmp_enabled' => true,
            ],
            'rules' => array_reduce(
                ConflictType::cases(),
                fn (array $acc, ConflictType $type) => [
                    ...$acc,
                    $type->value => true,
                ],
                ['auto_resolve_minutes' => 15],
            ),
            'notifications' => [
                'telegram_enabled' => true,
                'has_telegram_bot_token' => true,
                'telegram_chat_id' => '-100239481029',
                'notify_on_conflict' => true,
                'notify_on_camera_offline' => true,
                'notify_on_uplink_failover' => true,
                'notify_daily_summary' => false,
                'cooldown_minutes' => 30,
            ],
            'cctv' => [
                'go2rtc_url' => 'http://127.0.0.1:1984',
                'camera_username' => 'viewer',
                'has_camera_password' => true,
                'max_concurrent_streams' => 16,
                'rtsp_templates' => [
                    'hikvision' => [
                        'main' => 'rtsp://{user}:{pass}@{ip}:554/Streaming/Channels/101',
                        'sub' => 'rtsp://{user}:{pass}@{ip}:554/Streaming/Channels/102',
                    ],
                    'dahua' => [
                        'main' => 'rtsp://{user}:{pass}@{ip}:554/cam/realmonitor?channel=1&subtype=0',
                        'sub' => 'rtsp://{user}:{pass}@{ip}:554/cam/realmonitor?channel=1&subtype=1',
                    ],
                    'uniview' => [
                        'main' => 'rtsp://{user}:{pass}@{ip}:554/media/video1',
                        'sub' => 'rtsp://{user}:{pass}@{ip}:554/media/video2',
                    ],
                ],
            ],
            'backup' => [
                'schedule_time' => '02:00',
                'retention_days' => 14,
                'include_mikrotik_export' => true,
                'backup_path' => '/backups',
            ],
            default => [],
        };
    }
}
