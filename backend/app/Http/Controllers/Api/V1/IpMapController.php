<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\IpMap\BuildSegmentIpMap;
use App\Actions\IpMap\CheckIpAvailability;
use App\Actions\IpMap\SuggestNextFreeIp;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\CheckIpRequest;
use App\Models\Segment;
use App\Services\Mikrotik\Collector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

final class IpMapController extends Controller
{
    public function show(Segment $segment, BuildSegmentIpMap $buildSegmentIpMap, Collector $collector): JsonResponse
    {
        if (! app()->runningUnitTests() && Cache::add('netmap_live_sync_lock', true, 8)) {
            try {
                $collector->sync();
            } catch (\Throwable) {
                // Keep serving map even if live probe encounters an error
            }
        }

        return response()->json([
            'data' => $buildSegmentIpMap->handle($segment),
        ]);
    }

    public function nextFreeIp(Request $request, Segment $segment, SuggestNextFreeIp $suggestNextFreeIp): JsonResponse
    {
        $range = (string) $request->query('range', '');
        $start = null;
        $end = null;

        if (preg_match('/^(\d{1,3})-(\d{1,3})$/', $range, $matches) === 1) {
            $start = (int) $matches[1];
            $end = (int) $matches[2];
        }

        $ip = $suggestNextFreeIp->handle($segment, $start, $end);

        return response()->json([
            'data' => [
                'segment_id' => $segment->id,
                'ip' => $ip,
                'range' => $start !== null
                    ? ".{$start}–.{$end}"
                    : ".{$segment->static_start}–.{$segment->static_end}",
            ],
        ]);
    }

    public function check(CheckIpRequest $request, CheckIpAvailability $checkIpAvailability): JsonResponse
    {
        $ip = (string) $request->validated('ip');
        $segmentId = $request->filled('segment_id') ? (int) $request->validated('segment_id') : null;

        return response()->json([
            'data' => $checkIpAvailability->handle($ip, $segmentId),
        ]);
    }
}
