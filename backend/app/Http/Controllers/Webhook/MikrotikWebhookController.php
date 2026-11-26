<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\ArpObservation;
use App\Models\DhcpLease;
use App\Models\Segment;
use App\Models\Uplink;
use App\Services\Conflicts\RuleEngine;
use App\Support\NetworkFormat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MikrotikWebhookController extends Controller
{
    public function lease(Request $request, RuleEngine $ruleEngine): JsonResponse
    {
        $ip = trim((string) $request->query('ip', $request->input('ip', '')));
        $mac = NetworkFormat::normalizeMac((string) $request->query('mac', $request->input('mac', '')));
        $bound = (string) $request->query('bound', $request->input('bound', '1')) === '1';
        $server = (string) $request->query('server', $request->input('server', 'dhcp'));
        $hostName = (string) $request->query('hostname', $request->input('hostname', ''));

        if (! NetworkFormat::isValidIpv4NoLeadingZeros($ip) || ! NetworkFormat::isValidMac($mac)) {
            return response()->json([
                'message' => 'Format IP atau MAC pada webhook lease tidak valid.',
                'code' => 'validation_failed',
            ], 422);
        }

        /** @var string $validMac */
        $validMac = $mac;
        $segment = Segment::query()->get()->first(fn (Segment $s) => $s->containsIp($ip));

        if ($segment !== null) {
            DhcpLease::query()->updateOrCreate(
                [
                    'segment_id' => $segment->id,
                    'ip' => $ip,
                    'mac' => $validMac,
                ],
                [
                    'host_name' => $hostName !== '' ? $hostName : null,
                    'server' => $server,
                    'status' => $bound ? 'bound' : 'released',
                    'is_dynamic' => true,
                    'is_present' => $bound,
                    'last_seen_at' => now(),
                ],
            );

            if ($bound) {
                ArpObservation::query()->updateOrCreate(
                    [
                        'segment_id' => $segment->id,
                        'ip' => $ip,
                        'mac' => $validMac,
                    ],
                    [
                        'interface' => $segment->interface,
                        'is_dynamic' => true,
                        'is_complete' => true,
                        'first_seen_at' => now(),
                        'last_seen_at' => now(),
                    ],
                );
            }

            $ruleEngine->evaluateAll();
        }

        return response()->json(['status' => 'accepted'], 202);
    }

    public function netwatch(Request $request): JsonResponse
    {
        $name = trim((string) $request->query('name', $request->input('name', '')));
        $status = strtolower(trim((string) $request->query('status', $request->input('status', 'up'))));

        if ($name === '' || ! in_array($status, ['up', 'down', 'standby'], true)) {
            return response()->json([
                'message' => 'Parameter name dan status (up/down) wajib diisi.',
                'code' => 'validation_failed',
            ], 422);
        }

        Uplink::query()->where('name', $name)->update([
            'status' => $status,
            'last_changed_at' => now(),
        ]);

        return response()->json(['status' => 'accepted'], 202);
    }
}
