<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\IpReservation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin IpReservation
 */
final class IpReservationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'segment_id' => $this->segment_id,
            'segment_name' => $this->segment?->name,
            'segment_color' => $this->segment?->color,
            'ip_start' => $this->ip_start,
            'ip_end' => $this->ip_end,
            'start_octet' => $this->start_octet,
            'end_octet' => $this->end_octet,
            'range_label' => ".{$this->start_octet}–.{$this->end_octet}",
            'count' => ($this->end_octet - $this->start_octet) + 1,
            'label' => $this->label,
            'description' => $this->description,
            'created_at' => $this->created_at?->toIso8601ZuluString(),
        ];
    }
}
