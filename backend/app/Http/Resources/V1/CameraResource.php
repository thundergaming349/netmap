<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\Camera;
use App\Services\Cctv\Go2rtcClient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Camera
 */
final class CameraResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $device = $this->device;
        $nvr = $this->nvr;

        return [
            'id' => $this->id,
            'device_id' => $this->device_id,
            'nvr_id' => $this->nvr_id,
            'nvr_name' => $nvr?->name,
            'nvr_web_url' => ($device !== null && $device->ip !== '')
                ? "http://{$device->ip}"
                : ($nvr !== null ? ($nvr->web_url ?? "http://{$nvr->ip}") : null),
            'channel' => $this->channel,
            'name' => $device !== null ? $device->name : "Kamera #{$this->id}",
            'ip' => $device !== null ? $device->ip : '',
            'mac' => $device?->mac,
            'segment_id' => $device?->segment_id,
            'port' => $device?->port,
            'switch_name' => $device?->networkSwitch?->name,
            'brand' => $this->brand,
            'location_group' => $this->location_group,
            'stream_key' => $this->stream_key,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'fps' => $this->fps,
            'resolution' => $this->resolution,
            'latency_ms' => $this->device?->latency_ms,
            'last_ok_at' => $this->last_ok_at?->toIso8601ZuluString(),
            'last_snapshot_at' => $this->last_snapshot_at?->toIso8601ZuluString(),
            'stream_ready' => app(Go2rtcClient::class)->canAuthenticateRtsp(),
            'webrtc_sub_url' => "/stream/api/webrtc?src={$this->stream_key}_sub",
            'webrtc_main_url' => "/stream/api/webrtc?src={$this->stream_key}_main",
        ];
    }
}
