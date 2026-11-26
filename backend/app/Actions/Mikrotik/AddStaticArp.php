<?php

declare(strict_types=1);

namespace App\Actions\Mikrotik;

use App\Models\ArpObservation;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\Segment;
use App\Models\User;
use App\Services\Mikrotik\MikrotikClient;

final class AddStaticArp
{
    public function __construct(
        private readonly MikrotikClient $client,
    ) {}

    /**
     * @param  array{segment_id: int, ip: string, mac: string, comment?: string|null}  $data
     */
    public function handle(array $data, ?User $actor = null): ArpObservation
    {
        /** @var Segment $segment */
        $segment = Segment::query()->findOrFail($data['segment_id']);
        $ip = $data['ip'];
        $mac = strtolower($data['mac']);
        $comment = $data['comment'] ?? "ARP-{$ip}";

        $audit = AuditLog::record(
            action: 'mikrotik.arp.add_static',
            user: $actor,
            target: "{$ip} ({$mac}) on {$segment->interface}",
            after: [
                'segment_id' => $segment->id,
                'ip' => $ip,
                'mac' => $mac,
                'interface' => $segment->interface,
            ],
        );

        $routerResult = $this->client->addArp($ip, $mac, $segment->interface, "netmap:#{$audit->id} {$comment}");

        $obs = ArpObservation::query()->updateOrCreate(
            [
                'segment_id' => $segment->id,
                'ip' => $ip,
                'mac' => $mac,
            ],
            [
                'interface' => (string) ($routerResult['interface'] ?? $segment->interface),
                'is_dynamic' => false,
                'is_complete' => true,
                'first_seen_at' => now(),
                'last_seen_at' => now(),
            ],
        );

        Device::query()
            ->where('segment_id', $segment->id)
            ->where('ip', $ip)
            ->update(['is_arp_pinned' => true, 'mac' => $mac]);

        return $obs;
    }
}
