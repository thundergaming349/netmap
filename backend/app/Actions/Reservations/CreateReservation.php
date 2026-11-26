<?php

declare(strict_types=1);

namespace App\Actions\Reservations;

use App\Exceptions\ReservationOverlapException;
use App\Models\AuditLog;
use App\Models\IpReservation;
use App\Models\Segment;
use App\Models\User;

final class CreateReservation
{
    /**
     * @param  array{segment_id: int, start_octet: int, end_octet: int, label: string, description?: string|null}  $data
     */
    public function handle(array $data, ?User $actor = null): IpReservation
    {
        /** @var Segment $segment */
        $segment = Segment::query()->findOrFail($data['segment_id']);
        $start = min($data['start_octet'], $data['end_octet']);
        $end = max($data['start_octet'], $data['end_octet']);

        $overlap = IpReservation::query()
            ->where('segment_id', $segment->id)
            ->where('start_octet', '<=', $end)
            ->where('end_octet', '>=', $start)
            ->first();

        if ($overlap !== null) {
            throw new ReservationOverlapException(
                "Range .{$start}–.{$end} tumpang tindih dengan reservasi \"{$overlap->label}\" (.{$overlap->start_octet}–.{$overlap->end_octet})."
            );
        }

        $reservation = IpReservation::create([
            'segment_id' => $segment->id,
            'ip_start' => $segment->ipForOctet($start),
            'ip_end' => $segment->ipForOctet($end),
            'start_octet' => $start,
            'end_octet' => $end,
            'label' => $data['label'],
            'description' => $data['description'] ?? null,
            'created_by' => $actor?->id,
        ]);

        AuditLog::record(
            action: 'reservation.created',
            user: $actor,
            target: "{$segment->name} .{$start}–.{$end} ({$reservation->label})",
            after: [
                'segment_id' => $segment->id,
                'ip_start' => $reservation->ip_start,
                'ip_end' => $reservation->ip_end,
                'label' => $reservation->label,
            ],
        );

        return $reservation->load('segment');
    }
}
