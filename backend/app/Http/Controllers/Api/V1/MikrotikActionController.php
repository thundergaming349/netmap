<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Mikrotik\AddStaticArp;
use App\Actions\Mikrotik\BlockMacAddress;
use App\Actions\Mikrotik\MakeLeaseStatic;
use App\Actions\Mikrotik\RestartPoePort;
use App\Actions\Mikrotik\UnblockMacAddress;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\MikrotikWriteActionRequest;
use App\Models\AuditLog;
use App\Models\MacBlock;
use App\Models\Segment;
use App\Services\Mikrotik\Collector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MikrotikActionController extends Controller
{
    public function makeLeaseStatic(MikrotikWriteActionRequest $request, MakeLeaseStatic $action): JsonResponse
    {
        $firstSegment = Segment::query()->first();
        $segmentId = $request->integer('segment_id') ?: ($firstSegment !== null ? $firstSegment->id : 1);
        $ip = (string) $request->validated('ip');
        $mac = (string) $request->validated('mac');

        $lease = $action->handle([
            'segment_id' => $segmentId,
            'ip' => $ip,
            'mac' => $mac,
            'comment' => $request->validated('comment'),
        ], $request->user());

        return response()->json([
            'data' => [
                'id' => $lease->id,
                'ip' => $lease->ip,
                'mac' => $lease->mac,
                'routeros_id' => $lease->routeros_id,
            ],
            'message' => "Lease DHCP {$ip} (".strtoupper($mac).') berhasil dikunci menjadi static.',
        ]);
    }

    public function addArp(MikrotikWriteActionRequest $request, AddStaticArp $action): JsonResponse
    {
        $firstSegment = Segment::query()->first();
        $segmentId = $request->integer('segment_id') ?: ($firstSegment !== null ? $firstSegment->id : 1);
        $ip = (string) $request->validated('ip');
        $mac = (string) $request->validated('mac');

        $obs = $action->handle([
            'segment_id' => $segmentId,
            'ip' => $ip,
            'mac' => $mac,
            'comment' => $request->validated('comment'),
        ], $request->user());

        return response()->json([
            'data' => [
                'id' => $obs->id,
                'ip' => $obs->ip,
                'mac' => $obs->mac,
                'interface' => $obs->interface,
            ],
            'message' => "ARP static untuk {$ip} (".strtoupper($mac).') berhasil dipasang.',
        ]);
    }

    public function blockMac(MikrotikWriteActionRequest $request, BlockMacAddress $action): JsonResponse
    {
        $mac = (string) $request->validated('mac');
        $block = $action->handle([
            'mac' => $mac,
            'segment_id' => $request->filled('segment_id') ? $request->integer('segment_id') : null,
            'ip' => $request->validated('ip'),
            'reason' => $request->validated('reason'),
        ], $request->user());

        return response()->json([
            'data' => [
                'id' => $block->id,
                'mac' => $block->mac,
                'ip' => $block->ip,
                'reason' => $block->reason,
                'routeros_id' => $block->routeros_id,
            ],
            'message' => 'MAC '.strtoupper($mac).' berhasil diblokir di bridge filter MikroTik.',
        ], 201);
    }

    public function unblockMac(Request $request, MacBlock $macBlock, UnblockMacAddress $action): JsonResponse
    {
        $mac = strtoupper($macBlock->mac);
        $action->handle($macBlock, $request->user());

        return response()->json([
            'message' => "Blokir untuk MAC {$mac} berhasil dibuka.",
        ]);
    }

    public function restartPoe(Request $request, string $interface, RestartPoePort $action): JsonResponse
    {
        $result = $action->handle($interface, $request->user());

        return response()->json([
            'data' => $result,
            'message' => "Daya PoE pada port {$interface} berhasil di-restart.",
        ]);
    }

    public function sync(Request $request, Collector $collector): JsonResponse
    {
        $result = $collector->sync();

        AuditLog::record(
            action: 'mikrotik.sync',
            user: $request->user(),
            target: 'MikroTik-Core',
            after: $result,
        );

        return response()->json([
            'data' => [
                'job_id' => 'sync-'.now()->timestamp,
                ...$result,
            ],
            'message' => "Sinkronisasi MikroTik selesai ({$result['arp_updated']} entri ARP diperbarui).",
        ], 202);
    }

    public function scan(Request $request, Collector $collector): JsonResponse
    {
        $segmentId = $request->filled('segment_id') ? $request->integer('segment_id') : null;
        $segment = $segmentId !== null ? Segment::query()->find($segmentId) : null;
        $result = $collector->sync();

        AuditLog::record(
            action: 'mikrotik.scan_arp',
            user: $request->user(),
            target: $segment ? "Jalur {$segment->name}" : 'Semua Jalur',
            after: $result,
        );

        return response()->json([
            'data' => [
                'job_id' => 'scan-'.now()->timestamp,
                'segment_id' => $segment?->id,
                ...$result,
            ],
            'message' => $segment
                ? "Scan ARP aktif di jalur {$segment->name} selesai."
                : 'Scan ARP aktif di semua jalur selesai.',
        ], 202);
    }
}
