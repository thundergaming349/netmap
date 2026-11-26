<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\Nvr;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Nvr
 */
final class NvrResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $usagePercent = $this->capacity_bytes > 0
            ? round(($this->used_bytes / $this->capacity_bytes) * 100, 1)
            : 0.0;

        return [
            'id' => $this->id,
            'device_id' => $this->device_id,
            'name' => $this->name,
            'ip' => $this->ip,
            'brand' => $this->brand,
            'web_url' => $this->web_url ?? "http://{$this->ip}",
            'channel_count' => $this->channel_count,
            'camera_count' => $this->cameras()->count(),
            'capacity_bytes' => $this->capacity_bytes,
            'used_bytes' => $this->used_bytes,
            'usage_percent' => $usagePercent,
            'retention_days' => $this->retention_days,
            'status' => $this->status,
            'last_ok_at' => $this->last_ok_at?->toIso8601ZuluString(),
        ];
    }
}
