<?php

declare(strict_types=1);

namespace App\Actions\Mikrotik;

use App\Models\AuditLog;
use App\Models\MacBlock;
use App\Models\Router;
use App\Models\User;
use App\Services\Mikrotik\MikrotikClient;

final class BlockMacAddress
{
    public function __construct(
        private readonly MikrotikClient $client,
    ) {}

    /**
     * @param  array{mac: string, segment_id?: int|null, ip?: string|null, reason?: string|null}  $data
     */
    public function handle(array $data, ?User $actor = null): MacBlock
    {
        $mac = strtolower($data['mac']);
        $reason = $data['reason'] ?? 'Diblokir dari dashboard NetMap';
        $router = Router::query()->first();

        $audit = AuditLog::record(
            action: 'mikrotik.mac.block',
            user: $actor,
            target: strtoupper($mac),
            after: [
                'mac' => $mac,
                'ip' => $data['ip'] ?? null,
                'segment_id' => $data['segment_id'] ?? null,
                'reason' => $reason,
            ],
        );

        $result = $this->client->blockMac($mac, "netmap:#{$audit->id} {$reason}");

        return MacBlock::create([
            'router_id' => $router?->id,
            'segment_id' => $data['segment_id'] ?? null,
            'mac' => $mac,
            'ip' => $data['ip'] ?? null,
            'reason' => $reason,
            'routeros_id' => (string) ($result['.id'] ?? '*BLK'),
            'created_by' => $actor?->id,
        ]);
    }
}
