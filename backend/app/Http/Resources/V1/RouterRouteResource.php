<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\RouterRoute;
use App\Models\Segment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RouterRoute
 */
final class RouterRouteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $segmentIds = $this->segment_ids ?? [];
        $segments = empty($segmentIds)
            ? []
            : Segment::query()->whereIn('id', $segmentIds)->get(['id', 'name', 'color', 'vlan_id'])->toArray();

        return [
            'id' => $this->id,
            'dst_address' => $this->dst_address,
            'gateway' => $this->gateway,
            'routing_table' => $this->routing_table,
            'distance' => $this->distance,
            'scope' => $this->scope,
            'is_active' => $this->is_active,
            'is_dynamic' => $this->is_dynamic,
            'comment' => $this->comment,
            'segments' => $segments,
        ];
    }
}
