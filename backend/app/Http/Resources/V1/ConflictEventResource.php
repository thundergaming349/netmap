<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Actions\IpMap\SuggestNextFreeIp;
use App\Models\ConflictEvent;
use App\Models\ConflictEventLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ConflictEvent
 */
final class ConflictEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $suggestedFreeIp = $this->segment !== null
            ? app(SuggestNextFreeIp::class)->handle($this->segment)
            : null;

        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'severity' => $this->severity->value,
            'severity_label' => $this->severity->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'segment_id' => $this->segment_id,
            'segment' => $this->segment ? [
                'id' => $this->segment->id,
                'name' => $this->segment->name,
                'slug' => $this->segment->slug,
                'vlan_id' => $this->segment->vlan_id,
                'color' => $this->segment->color,
                'interface' => $this->segment->interface,
            ] : null,
            'ip' => $this->ip,
            'macs' => $this->macs,
            'title' => $this->title,
            'summary' => $this->summary,
            'suggestion' => $this->suggestion ?? $this->type->defaultSuggestion($suggestedFreeIp),
            'suggested_free_ip' => $suggestedFreeIp,
            'source' => $this->source,
            'device' => $this->device ? [
                'id' => $this->device->id,
                'name' => $this->device->name,
                'type' => $this->device->type->value,
                'type_label' => $this->device->type->label(),
                'mac' => $this->device->mac,
                'location' => $this->device->location,
                'port' => $this->device->port,
            ] : null,
            'opened_at' => $this->opened_at->toIso8601ZuluString(),
            'last_detected_at' => $this->last_detected_at->toIso8601ZuluString(),
            'resolved_at' => $this->resolved_at?->toIso8601ZuluString(),
            'resolved_by_name' => $this->resolver ? ($this->resolver->name ?? $this->resolver->email) : null,
            'resolution_note' => $this->resolution_note,
            'logs' => $this->whenLoaded('logs', fn () => $this->logs->map(fn (ConflictEventLog $log) => [
                'id' => $log->id,
                'logged_at' => $log->logged_at->toIso8601ZuluString(),
                'message' => $log->message,
                'actor_name' => $log->actor ? ($log->actor->name ?? $log->actor->email) : 'Sistem NetMap',
            ])->values()->all()),
        ];
    }
}
