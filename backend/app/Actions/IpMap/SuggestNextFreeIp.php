<?php

declare(strict_types=1);

namespace App\Actions\IpMap;

use App\Models\ArpObservation;
use App\Models\Device;
use App\Models\DhcpLease;
use App\Models\IpReservation;
use App\Models\Segment;

final class SuggestNextFreeIp
{
    public function handle(Segment $segment, ?int $rangeStart = null, ?int $rangeEnd = null): ?string
    {
        $start = max(2, $rangeStart ?? $segment->static_start);
        $end = min(254, $rangeEnd ?? $segment->static_end);

        if ($start > $end) {
            return null;
        }

        $usedIps = Device::query()
            ->where('segment_id', $segment->id)
            ->pluck('ip')
            ->merge(
                DhcpLease::query()
                    ->where('segment_id', $segment->id)
                    ->where('is_present', true)
                    ->pluck('ip'),
            )
            ->merge(
                ArpObservation::query()
                    ->where('segment_id', $segment->id)
                    ->recent(1440)
                    ->pluck('ip'),
            )
            ->unique()
            ->flip();

        $reservations = IpReservation::query()
            ->where('segment_id', $segment->id)
            ->get();

        $gatewayOctet = $segment->octetOf($segment->gateway) ?? 1;

        for ($octet = $start; $octet <= $end; $octet++) {
            if ($octet === $gatewayOctet) {
                continue;
            }

            $ip = $segment->ipForOctet($octet);

            if ($segment->isInPool($ip)) {
                continue;
            }

            if ($usedIps->has($ip)) {
                continue;
            }

            $reserved = $reservations->contains(fn (IpReservation $res) => $res->containsOctet($octet));
            if ($reserved) {
                continue;
            }

            return $ip;
        }

        return null;
    }
}
