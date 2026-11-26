<?php

declare(strict_types=1);

namespace App\Actions\IpMap;

use App\Models\ArpObservation;
use App\Models\Device;
use App\Models\DhcpLease;
use App\Models\IpReservation;
use App\Models\Segment;

final class CheckIpAvailability
{
    public function __construct(
        private readonly SuggestNextFreeIp $suggestNextFreeIp,
    ) {}

    /**
     * @return array{
     *   ip: string,
     *   result: 'free'|'used'|'reserved'|'in_pool'|'gateway'|'network'|'out_of_segment',
     *   safe: bool,
     *   title: string,
     *   message: string,
     *   segment_id: int|null,
     *   segment_name: string|null,
     *   suggestion: string|null,
     *   holder: array<string, mixed>|null
     * }
     */
    public function handle(string $ip, ?int $segmentId = null): array
    {
        $segment = $segmentId !== null
            ? Segment::query()->find($segmentId)
            : Segment::query()->get()->first(fn (Segment $s) => $s->containsIp($ip));

        if ($segment === null || ! $segment->containsIp($ip)) {
            $fallbackSegment = $segment ?? Segment::query()->first();
            $suggestion = $fallbackSegment ? $this->suggestNextFreeIp->handle($fallbackSegment) : null;

            return [
                'ip' => $ip,
                'result' => 'out_of_segment',
                'safe' => false,
                'title' => 'Di luar subnet jalur',
                'message' => $segment !== null
                    ? "IP {$ip} tidak berada di dalam subnet jalur {$segment->name} ({$segment->subnet})."
                    : "IP {$ip} tidak cocok dengan subnet jalur mana pun di NetMap.",
                'segment_id' => $segment?->id,
                'segment_name' => $segment?->name,
                'suggestion' => $suggestion,
                'holder' => null,
            ];
        }

        $octet = $segment->octetOf($ip) ?? 0;
        $suggestion = $this->suggestNextFreeIp->handle($segment);

        if ($octet === 0 || $octet === 255) {
            return [
                'ip' => $ip,
                'result' => 'network',
                'safe' => false,
                'title' => 'Alamat Network / Broadcast',
                'message' => "IP {$ip} adalah alamat network/broadcast dan tidak boleh dipasang di perangkat.",
                'segment_id' => $segment->id,
                'segment_name' => $segment->name,
                'suggestion' => $suggestion,
                'holder' => null,
            ];
        }

        if ($ip === $segment->gateway) {
            return [
                'ip' => $ip,
                'result' => 'gateway',
                'safe' => false,
                'title' => 'Alamat Gateway MikroTik',
                'message' => "IP {$ip} adalah gateway jalur {$segment->name} di {$segment->interface}. Jangan dipakai di perangkat lain.",
                'segment_id' => $segment->id,
                'segment_name' => $segment->name,
                'suggestion' => $suggestion,
                'holder' => ['name' => 'Gateway MikroTik', 'mac' => null, 'type' => 'router'],
            ];
        }

        $device = Device::query()->where('segment_id', $segment->id)->where('ip', $ip)->first();
        if ($device !== null) {
            return [
                'ip' => $ip,
                'result' => 'used',
                'safe' => false,
                'title' => "Sudah dipakai {$device->name}",
                'message' => "IP {$ip} sudah terdaftar untuk {$device->name}".($device->mac ? ' ('.strtoupper($device->mac).')' : '').($suggestion ? ". Gunakan {$suggestion} sebagai gantinya." : '.'),
                'segment_id' => $segment->id,
                'segment_name' => $segment->name,
                'suggestion' => $suggestion,
                'holder' => [
                    'id' => $device->id,
                    'name' => $device->name,
                    'mac' => $device->mac,
                    'type' => $device->type->label(),
                    'location' => $device->location,
                ],
            ];
        }

        $lease = DhcpLease::query()
            ->where('segment_id', $segment->id)
            ->where('ip', $ip)
            ->where('is_present', true)
            ->first();

        if ($lease !== null) {
            $holderName = $lease->host_name ?? 'Perangkat DHCP';

            return [
                'ip' => $ip,
                'result' => 'used',
                'safe' => false,
                'title' => "Sedang dipakai lease DHCP ({$holderName})",
                'message' => "IP {$ip} sedang aktif dipakai oleh {$holderName} (".strtoupper($lease->mac).')'.($suggestion ? ". Gunakan {$suggestion}." : '.'),
                'segment_id' => $segment->id,
                'segment_name' => $segment->name,
                'suggestion' => $suggestion,
                'holder' => [
                    'name' => $holderName,
                    'mac' => $lease->mac,
                    'type' => 'DHCP Lease',
                ],
            ];
        }

        $recentArp = ArpObservation::query()
            ->where('segment_id', $segment->id)
            ->where('ip', $ip)
            ->recent(60)
            ->first();

        if ($recentArp !== null) {
            return [
                'ip' => $ip,
                'result' => 'used',
                'safe' => false,
                'title' => 'Terdeteksi aktif di tabel ARP',
                'message' => "IP {$ip} baru saja terlihat aktif dengan MAC ".strtoupper($recentArp->mac).($suggestion ? ". Gunakan {$suggestion}." : '.'),
                'segment_id' => $segment->id,
                'segment_name' => $segment->name,
                'suggestion' => $suggestion,
                'holder' => [
                    'name' => 'Perangkat belum terdaftar',
                    'mac' => $recentArp->mac,
                    'type' => 'ARP Aktif',
                ],
            ];
        }

        $reservation = IpReservation::query()
            ->where('segment_id', $segment->id)
            ->get()
            ->first(fn (IpReservation $res) => $res->containsOctet($octet));

        if ($reservation !== null) {
            return [
                'ip' => $ip,
                'result' => 'reserved',
                'safe' => true,
                'title' => "Masuk range reservasi ({$reservation->label})",
                'message' => "IP {$ip} kosong dan berada di reservasi \"{$reservation->label}\" (.{$reservation->start_octet}–.{$reservation->end_octet}). Pastikan perangkat memang untuk keperluan tersebut.",
                'segment_id' => $segment->id,
                'segment_name' => $segment->name,
                'suggestion' => $suggestion,
                'holder' => [
                    'name' => $reservation->label,
                    'mac' => null,
                    'type' => 'Reservasi',
                ],
            ];
        }

        if ($segment->isInPool($ip)) {
            return [
                'ip' => $ip,
                'result' => 'in_pool',
                'safe' => false,
                'title' => 'Rawan — Berada di dalam pool DHCP',
                'message' => "IP {$ip} saat ini kosong, tetapi masuk range pool DHCP ({$segment->pool_start}–{$segment->pool_end}). Jangan dipasang static manual tanpa mengunci lease static".($suggestion ? ", atau gunakan {$suggestion}." : '.'),
                'segment_id' => $segment->id,
                'segment_name' => $segment->name,
                'suggestion' => $suggestion,
                'holder' => null,
            ];
        }

        return [
            'ip' => $ip,
            'result' => 'free',
            'safe' => true,
            'title' => 'Aman dipakai',
            'message' => "IP {$ip} kosong di jalur {$segment->name}, tidak bentrok dengan ARP/DHCP, dan berada di luar pool DHCP.",
            'segment_id' => $segment->id,
            'segment_name' => $segment->name,
            'suggestion' => $ip,
            'holder' => null,
        ];
    }
}
