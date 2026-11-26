<?php

declare(strict_types=1);

namespace App\Actions\Reservations;

use App\Models\AuditLog;
use App\Models\IpReservation;
use App\Models\User;

final class DeleteReservation
{
    public function handle(IpReservation $reservation, ?User $actor = null): void
    {
        $before = [
            'id' => $reservation->id,
            'segment_id' => $reservation->segment_id,
            'ip_start' => $reservation->ip_start,
            'ip_end' => $reservation->ip_end,
            'label' => $reservation->label,
        ];

        $reservation->delete();

        AuditLog::record(
            action: 'reservation.deleted',
            user: $actor,
            target: "{$reservation->label} ({$reservation->ip_start}–{$reservation->ip_end})",
            before: $before,
        );
    }
}
