<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\SegmentResource;
use App\Models\AuditLog;
use App\Models\Segment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class SegmentController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $segments = Segment::query()->orderBy('vlan_id')->get();

        return SegmentResource::collection($segments);
    }

    public function show(Segment $segment): SegmentResource
    {
        return SegmentResource::make($segment);
    }

    public function update(Request $request, Segment $segment): SegmentResource
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:80'],
            'color' => ['sometimes', 'required', 'string', 'max:32'],
            'static_start' => ['sometimes', 'required', 'integer', 'min:2', 'max:254'],
            'static_end' => ['sometimes', 'required', 'integer', 'min:2', 'max:254'],
            'egress' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $segment->update($validated);

        AuditLog::record(
            action: 'segment.updated',
            user: $request->user(),
            target: $segment->name,
            after: $validated,
        );

        return SegmentResource::make($segment)->additional(['message' => 'Pengaturan jalur disimpan.']);
    }
}
