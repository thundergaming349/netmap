<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Conflicts\ResolveConflict;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ConflictEventResource;
use App\Models\ConflictEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ConflictEventController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = ConflictEvent::query()
            ->with(['segment', 'device', 'resolver', 'logs.actor'])
            ->orderByRaw("CASE status WHEN 'active' THEN 0 ELSE 1 END")
            ->orderByRaw("CASE severity WHEN 'conflict' THEN 0 WHEN 'risk' THEN 1 ELSE 2 END")
            ->orderByDesc('last_detected_at');

        $status = (string) $request->query('status', '');
        if ($status === 'active' || $status === 'resolved') {
            $query->where('status', $status);
        }

        if ($request->filled('segment_id')) {
            $query->where('segment_id', $request->integer('segment_id'));
        }

        if ($request->filled('type')) {
            $query->where('type', (string) $request->query('type'));
        }

        return ConflictEventResource::collection($query->get());
    }

    public function show(ConflictEvent $conflictEvent): ConflictEventResource
    {
        return ConflictEventResource::make(
            $conflictEvent->load(['segment', 'device', 'resolver', 'logs.actor'])
        );
    }

    public function resolve(Request $request, ConflictEvent $conflictEvent, ResolveConflict $resolveConflict): ConflictEventResource
    {
        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $resolved = $resolveConflict->handle(
            event: $conflictEvent,
            actor: $request->user(),
            note: $validated['note'] ?? null,
        );

        return ConflictEventResource::make($resolved)->additional([
            'message' => 'Konflik ditandai selesai.',
        ]);
    }
}
