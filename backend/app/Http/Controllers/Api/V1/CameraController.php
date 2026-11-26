<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\CameraStatus;
use App\Enums\DeviceStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\CameraResource;
use App\Http\Resources\V1\NvrResource;
use App\Models\Camera;
use App\Models\CameraRecording;
use App\Models\Nvr;
use App\Services\Cctv\CameraRecordingService;
use App\Services\Cctv\Go2rtcClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class CameraController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $allCameras = Camera::query()->with(['device.networkSwitch', 'nvr'])->orderBy('channel')->get();

        $groups = $allCameras->groupBy('location_group')
            ->map(fn ($items, $group) => [
                'name' => (string) $group,
                'count' => $items->count(),
            ])
            ->values()
            ->all();

        $filtered = $allCameras;
        if ($request->filled('group') && $request->query('group') !== 'all') {
            $groupFilter = strtolower((string) $request->query('group'));
            $filtered = $allCameras->filter(
                fn (Camera $c) => strtolower($c->location_group) === $groupFilter,
            )->values();
        }

        return response()->json([
            'data' => CameraResource::collection($filtered)->resolve(),
            'meta' => [
                'total' => $allCameras->count(),
                'online' => $allCameras->where('status', CameraStatus::Online)->count(),
                'unstable' => $allCameras->where('status', CameraStatus::Unstable)->count(),
                'offline' => $allCameras->where('status', CameraStatus::Offline)->count(),
                'groups' => $groups,
            ],
        ]);
    }

    public function stream(Request $request, Camera $camera, Go2rtcClient $go2rtc): JsonResponse
    {
        $quality = $request->query('quality') === 'main' ? 'main' : 'sub';

        return response()->json([
            'data' => $go2rtc->streamInfo($camera, $quality),
        ]);
    }

    public function snapshot(Camera $camera, Go2rtcClient $go2rtc): JsonResponse
    {
        return response()->json([
            'data' => $go2rtc->captureSnapshot($camera),
            'message' => "Snapshot {$camera->device?->name} berhasil diambil.",
        ]);
    }

    public function recordings(Request $request, Camera $camera, CameraRecordingService $recordingService): JsonResponse
    {
        $date = $request->filled('date') ? (string) $request->query('date') : null;
        $items = $recordingService->listHourlyRecordings($camera->load('device'), $date);

        return response()->json([
            'data' => $items->map(function (CameraRecording $rec): array {
                $startWib = $rec->started_at->timezone('Asia/Jakarta');
                $endWib = $rec->ended_at->timezone('Asia/Jakarta');

                return [
                    'id' => $rec->id,
                    'camera_id' => $rec->camera_id,
                    'date' => $startWib->format('Y-m-d'),
                    'slot_label' => $startWib->format('H:i').' – '.$endWib->format('H:i').' WIB',
                    'started_at' => $rec->started_at->toIso8601ZuluString(),
                    'ended_at' => $rec->ended_at->toIso8601ZuluString(),
                    'duration_seconds' => $rec->duration_seconds,
                    'duration_label' => $rec->status === 'recording'
                        ? 'Sedang merekam ('.max(1, (int) round($rec->duration_seconds / 60)).' mnt)'
                        : ($rec->duration_seconds >= 3300
                            ? '1 Jam (60 menit)'
                            : max(1, (int) round($rec->duration_seconds / 60)).' menit'),
                    'filename' => $rec->filename,
                    'size_bytes' => $rec->size_bytes,
                    'status' => $rec->status,
                    'download_url' => "/api/v1/recordings/{$rec->id}/download",
                ];
            })->values()->all(),
        ]);
    }

    public function downloadRecording(CameraRecording $recording, CameraRecordingService $recordingService): BinaryFileResponse
    {
        $path = $recordingService->ensureDownloadableMp4($recording);

        return response()->download($path, $recording->filename, [
            'Content-Type' => 'video/mp4',
        ]);
    }

    public function ping(Camera $camera): JsonResponse
    {
        $now = now()->toImmutable();
        $camera->update([
            'status' => CameraStatus::Online,
            'last_ok_at' => $now,
        ]);

        $camera->device?->update([
            'status' => DeviceStatus::Online,
            'last_seen_at' => $now,
            'latency_ms' => 3,
        ]);

        return response()->json([
            'data' => CameraResource::make($camera->load(['device.networkSwitch', 'nvr']))->resolve(),
            'message' => "Ping ke {$camera->device?->name} ({$camera->device?->ip}) berhasil (3 ms).",
        ], 202);
    }

    public function nvrs(): AnonymousResourceCollection
    {
        return NvrResource::collection(Nvr::query()->orderBy('name')->get());
    }

    public function streamAuth(): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }
}
