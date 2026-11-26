<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Camera;
use App\Models\CameraRecording;
use App\Models\User;
use App\Services\Cctv\CameraRecordingService;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    $this->seed(DemoSeeder::class);
});

it('lists hourly recordings and prunes recordings older than 7 days automatically', function (): void {
    $viewer = User::factory()->active()->withRole(Role::CctvViewer)->create();
    $camera = Camera::query()->firstOrFail();

    $oldStart = CarbonImmutable::now('Asia/Jakarta')->subDays(8)->setTime(10, 0, 0);
    $oldRelPath = "recordings/{$camera->id}/expired_8_days_ago.mp4";
    Storage::disk('local')->put($oldRelPath, str_repeat('x', 8192));

    $expiredRec = CameraRecording::query()->create([
        'camera_id' => $camera->id,
        'started_at' => $oldStart,
        'ended_at' => $oldStart->addHour(),
        'duration_seconds' => 3600,
        'filename' => 'expired_8_days_ago.mp4',
        'file_path' => $oldRelPath,
        'size_bytes' => 8192,
        'status' => 'ready',
    ]);

    $response = $this->actingAs($viewer)
        ->getJson("/api/v1/cameras/{$camera->id}/recordings")
        ->assertOk();

    expect(CameraRecording::query()->whereKey($expiredRec->id)->exists())->toBeFalse()
        ->and(Storage::disk('local')->exists($oldRelPath))->toBeFalse()
        ->and($response->json('data'))->not->toBeEmpty()
        ->and($response->json('data.0.download_url'))->toStartWith('/api/v1/recordings/');
});

it('downloads a real playable H.264 MP4 video and never a corrupt 450B stub', function (): void {
    $viewer = User::factory()->active()->withRole(Role::CctvViewer)->create();
    $camera = Camera::query()->firstOrFail();

    $listResp = $this->actingAs($viewer)
        ->getJson("/api/v1/cameras/{$camera->id}/recordings")
        ->assertOk();

    $recordingId = (int) $listResp->json('data.0.id');
    $recording = CameraRecording::query()->findOrFail($recordingId);

    $downloadResp = $this->actingAs($viewer)
        ->get("/api/v1/recordings/{$recording->id}/download")
        ->assertOk()
        ->assertHeader('content-type', 'video/mp4');

    $file = $downloadResp->baseResponse->getFile();
    $filePath = $file->getPathname();
    $bytes = (string) file_get_contents($filePath);

    expect(strlen($bytes))->toBeGreaterThan(4096)
        ->and(str_contains($bytes, 'ftyp'))->toBeTrue()
        ->and(str_contains($bytes, 'moov'))->toBeTrue()
        ->and(str_contains($bytes, 'mdat'))->toBeTrue();

    /** @var CameraRecordingService $service */
    $service = app(CameraRecordingService::class);
    $probe = $service->probeStreamInfo($filePath);

    expect($probe['has_video'])->toBeTrue()
        ->and($probe['codec'])->toBe('h264')
        ->and($probe['duration_seconds'])->toBeGreaterThanOrEqual(1);
});

it('converts recorded HEVC camera footage into universally playable H.264 MP4 on download', function (): void {
    $viewer = User::factory()->active()->withRole(Role::CctvViewer)->create();
    $camera = Camera::query()->firstOrFail();

    /** @var CameraRecordingService $service */
    $service = app(CameraRecordingService::class);
    $slotStart = CarbonImmutable::now('Asia/Jakarta')->subHour()->setTime(8, 0, 0);
    $meta = $service->slotMetadataForCamera($camera, $slotStart);
    $rawFullPath = Storage::disk('local')->path($meta['file_path']);

    $service->generateSyntheticVideoFile($rawFullPath, 'hevc');
    expect($service->probeStreamInfo($rawFullPath)['codec'])->toBe('hevc');

    $recording = CameraRecording::query()->create([
        'camera_id' => $camera->id,
        'started_at' => $meta['slot_start'],
        'ended_at' => $meta['slot_end'],
        'duration_seconds' => 2,
        'filename' => $meta['filename'],
        'file_path' => $meta['file_path'],
        'size_bytes' => (int) filesize($rawFullPath),
        'status' => 'ready',
    ]);

    $downloadResp = $this->actingAs($viewer)
        ->get("/api/v1/recordings/{$recording->id}/download")
        ->assertOk()
        ->assertHeader('content-type', 'video/mp4');

    $exportedPath = $downloadResp->baseResponse->getFile()->getPathname();
    $exportedProbe = $service->probeStreamInfo($exportedPath);

    expect((int) filesize($exportedPath))->toBeGreaterThan(4096)
        ->and($exportedProbe['has_video'])->toBeTrue()
        ->and($exportedProbe['codec'])->toBe('h264');
});
