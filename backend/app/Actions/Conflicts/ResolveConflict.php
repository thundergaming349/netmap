<?php

declare(strict_types=1);

namespace App\Actions\Conflicts;

use App\Enums\ConflictStatus;
use App\Enums\DeviceStatus;
use App\Models\AuditLog;
use App\Models\ConflictEvent;
use App\Models\ConflictEventLog;
use App\Models\User;

final class ResolveConflict
{
    public function handle(ConflictEvent $event, ?User $actor = null, ?string $note = null): ConflictEvent
    {
        $now = now();
        $actorName = $actor !== null ? ($actor->name ?? $actor->email) : 'Admin';
        $resolutionNote = $note !== null && trim($note) !== ''
            ? trim($note)
            : "Ditandai selesai oleh {$actorName}.";

        $event->update([
            'status' => ConflictStatus::Resolved,
            'resolved_at' => $now,
            'resolved_by' => $actor?->id,
            'resolution_note' => $resolutionNote,
        ]);

        if ($event->device !== null && $event->device->status === DeviceStatus::Conflict) {
            $event->device->update(['status' => DeviceStatus::Online]);
        }

        ConflictEventLog::create([
            'conflict_event_id' => $event->id,
            'actor_id' => $actor?->id,
            'logged_at' => $now,
            'message' => "Ditandai selesai: {$resolutionNote}",
        ]);

        AuditLog::record(
            action: 'conflict.resolved',
            user: $actor,
            target: "{$event->type->value} {$event->ip}",
            after: [
                'conflict_id' => $event->id,
                'status' => 'resolved',
                'note' => $resolutionNote,
            ],
        );

        return $event->load(['segment', 'device', 'resolver', 'logs.actor']);
    }
}
