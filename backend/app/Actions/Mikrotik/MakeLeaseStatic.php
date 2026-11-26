<?php

declare(strict_types=1);

namespace App\Actions\Mikrotik;

use App\Models\AuditLog;
use App\Models\Device;
use App\Models\DhcpLease;
use App\Models\Segment;
use App\Models\User;
use App\Services\Mikrotik\MikrotikClient;

final class MakeLeaseStatic
{
    public function __construct(
        private readonly MikrotikClient $client,
    ) {}

    /**
     * @param  array{segment_id: int, ip: string, mac: string, comment?: string|null}  $data
     */
    public function handle(array $data, ?User $actor = null): DhcpLease
    {
        /** @var Segment $segment */
        $segment = Segment::query()->findOrFail($data['segment_id']);
        $ip = $data['ip'];
        $mac = strtolower($data['mac']);
        $label = $data['comment'] ?? "Static-{$ip}";

        $audit = AuditLog::record(
            action: 'mikrotik.lease.make_static',
            user: $actor,
            target: "{$ip} ({$mac})",
            after: [
                'segment_id' => $segment->id,
                'ip' => $ip,
                'mac' => $mac,
                'comment' => $label,
            ],
        );

        $routerComment = "netmap:#{$audit->id} {$label}";
        $routerResult = $this->client->makeLeaseStatic($ip, $mac, $routerComment);

        $lease = DhcpLease::query()->updateOrCreate(
            [
                'segment_id' => $segment->id,
                'ip' => $ip,
                'mac' => $mac,
            ],
            [
                'host_name' => $label,
                'server' => 'dhcp-'.$segment->slug,
                'status' => 'bound',
                'is_dynamic' => false,
                'is_present' => true,
                'routeros_id' => (string) ($routerResult['.id'] ?? '*STATIC'),
                'comment' => $routerComment,
                'last_seen_at' => now(),
            ],
        );

        Device::query()
            ->where('segment_id', $segment->id)
            ->where('ip', $ip)
            ->update(['is_static_lease' => true, 'mac' => $mac]);

        return $lease;
    }
}
