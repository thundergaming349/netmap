<?php

declare(strict_types=1);

namespace App\Actions\Mikrotik;

use App\Models\AuditLog;
use App\Models\MacBlock;
use App\Models\User;
use App\Services\Mikrotik\MikrotikClient;

final class UnblockMacAddress
{
    public function __construct(
        private readonly MikrotikClient $client,
    ) {}

    public function handle(MacBlock $macBlock, ?User $actor = null): void
    {
        if ($macBlock->routeros_id !== null) {
            $this->client->unblockMac($macBlock->routeros_id);
        }

        $before = [
            'id' => $macBlock->id,
            'mac' => $macBlock->mac,
            'ip' => $macBlock->ip,
            'reason' => $macBlock->reason,
        ];

        $macBlock->delete();

        AuditLog::record(
            action: 'mikrotik.mac.unblock',
            user: $actor,
            target: strtoupper($before['mac']),
            before: $before,
        );
    }
}
