<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\CameraStatus;
use App\Models\Camera;
use App\Models\CameraRecording;
use App\Services\Cctv\CameraRecordingService;
use App\Services\Cctv\Go2rtcClient;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

#[Signature('cctv:record-daemon {--max-cameras=140 : Batas maksimum proses perekaman paralel} {--once : Jalankan satu siklus pemeliharaan lalu berhenti}')]
#[Description('Rekam seluruh kamera CCTV aktif secara kontinu per durasi 1 jam di latar belakang')]
final class RecordCctvDaemonCommand extends Command
{
    private const MIN_AVAILABLE_RAM_KB = 512_000;

    private bool $shouldStop = false;

    /**
     * @var array<int, array{
     *     process: resource,
     *     pipes: array<int, resource>,
     *     camera: Camera,
     *     slot_start: CarbonImmutable,
     *     slot_end: CarbonImmutable,
     *     filename: string,
     *     file_path: string,
     *     raw_path: string,
     *     target_path: string,
     *     is_part: bool,
     *     started_ts: int
     * }>
     */
    private array $workers = [];

    /** @var array<int, int> */
    private array $attemptCursor = [];

    /** @var array<int, int> */
    private array $failureCount = [];

    /** @var array<int, int> */
    private array $backoffUntil = [];

    public function handle(CameraRecordingService $recordingService, Go2rtcClient $go2rtc): int
    {
        if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, function (): void {
                $this->shouldStop = true;
            });
            pcntl_signal(SIGINT, function (): void {
                $this->shouldStop = true;
            });
        }

        $maxCameras = max(1, (int) $this->option('max-cameras'));
        $runOnce = (bool) $this->option('once');

        if ($runOnce) {
            $recordingService->runBackgroundRecordingTick();

            return self::SUCCESS;
        }

        $this->info("Memulai daemon perekaman CCTV latar belakang (maks {$maxCameras} kamera)...");

        $activeSlotKey = '';
        $lastDbSyncTs = 0;
        $lastPruneTs = 0;
        $lastCameraRefreshTs = 0;
        /** @var Collection<int, Camera> $onlineCameras */
        $onlineCameras = collect();

        while (! $this->shouldStop) {
            $nowJakarta = CarbonImmutable::now('Asia/Jakarta');
            $slotStart = $nowJakarta->setTime((int) $nowJakarta->format('G'), 0, 0);
            $slotEnd = $slotStart->addHour();
            $slotKey = $slotStart->format('Y-m-d_H');

            if ($slotKey !== $activeSlotKey) {
                if ($activeSlotKey !== '') {
                    $this->terminateAllWorkers();
                }
                $recordingService->runBackgroundRecordingTick();
                $activeSlotKey = $slotKey;
                $lastPruneTs = time();
                $lastCameraRefreshTs = 0;
            }

            $this->reapWorkers($recordingService);

            $nowTs = time();
            if ($nowTs - $lastCameraRefreshTs >= 30) {
                $onlineCameras = Camera::query()
                    ->with('device')
                    ->where('status', '!=', CameraStatus::Offline)
                    ->orderBy('channel')
                    ->get();
                $lastCameraRefreshTs = $nowTs;
            }

            $this->spawnMissingWorkers(
                cameras: $onlineCameras,
                maxCameras: $maxCameras,
                slotStart: $slotStart,
                slotEnd: $slotEnd,
                recordingService: $recordingService,
                go2rtc: $go2rtc,
            );

            if ($nowTs - $lastDbSyncTs >= 8) {
                $this->syncActiveWorkerRows($nowJakarta);
                $lastDbSyncTs = $nowTs;
            }

            if ($nowTs - $lastPruneTs >= 60) {
                $recordingService->pruneExpiredRecordings();
                $lastPruneTs = $nowTs;
            }

            sleep(2);
        }

        $this->terminateAllWorkers();
        $recordingService->runBackgroundRecordingTick();

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, Camera>  $cameras
     */
    private function spawnMissingWorkers(
        Collection $cameras,
        int $maxCameras,
        CarbonImmutable $slotStart,
        CarbonImmutable $slotEnd,
        CameraRecordingService $recordingService,
        Go2rtcClient $go2rtc,
    ): void {
        if (count($this->workers) >= $maxCameras) {
            return;
        }

        $nowTs = time();
        $remainingSeconds = $slotEnd->getTimestamp() - $nowTs;
        if ($remainingSeconds < 4) {
            return;
        }

        $ffmpeg = $recordingService->ffmpegBinaryPath();
        $disk = Storage::disk('local');

        foreach ($cameras as $camera) {
            if ($this->shouldStop || count($this->workers) >= $maxCameras) {
                break;
            }

            $camId = $camera->id;
            if (isset($this->workers[$camId])) {
                continue;
            }

            if (($this->backoffUntil[$camId] ?? 0) > $nowTs) {
                continue;
            }

            if ($this->availableRamKb() < self::MIN_AVAILABLE_RAM_KB) {
                break;
            }

            $sources = $go2rtc->buildRecordingRtspSources($camera);
            if ($sources === []) {
                continue;
            }

            // Each source is tried over TCP first (even cursor), then UDP (odd cursor) for cameras rejecting TCP interleave.
            $cursor = $this->attemptCursor[$camId] ?? 0;
            $sourceIdx = intdiv($cursor, 2) % count($sources);
            $transport = ($cursor % 2 === 0) ? 'tcp' : 'udp';
            $rtspUrl = $sources[$sourceIdx];

            $meta = $recordingService->slotMetadataForCamera($camera, $slotStart);
            $rawPath = $disk->path($meta['file_path']);
            $dir = dirname($rawPath);
            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }

            $existingBytes = $this->fileSize($rawPath);
            $isPart = $existingBytes >= CameraRecordingService::MIN_VALID_MP4_BYTES;
            $targetPath = $isPart ? "{$rawPath}.part.mp4" : $rawPath;

            if ($isPart) {
                @unlink($targetPath);
            }

            $command = [
                $ffmpeg,
                '-nostdin',
                '-hide_banner',
                '-loglevel',
                'error',
                '-fflags',
                '+genpts+discardcorrupt',
                ...($transport === 'tcp' ? ['-rtsp_transport', 'tcp'] : []),
                '-timeout',
                '8000000',
                '-y',
                '-i',
                $rtspUrl,
                '-t',
                (string) $remainingSeconds,
                '-map',
                '0:v:0',
                '-an',
                '-c:v',
                'copy',
                '-movflags',
                '+frag_keyframe+empty_moov+default_base_moof',
                $targetPath,
            ];

            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];

            $process = @proc_open($command, $descriptors, $pipes);
            if (! is_resource($process)) {
                continue;
            }

            fclose($pipes[0]);
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);

            $this->workers[$camId] = [
                'process' => $process,
                'pipes' => [1 => $pipes[1], 2 => $pipes[2]],
                'camera' => $camera,
                'slot_start' => $meta['slot_start'],
                'slot_end' => $meta['slot_end'],
                'filename' => $meta['filename'],
                'file_path' => $meta['file_path'],
                'raw_path' => $rawPath,
                'target_path' => $targetPath,
                'is_part' => $isPart,
                'started_ts' => $nowTs,
            ];
        }
    }

    private function reapWorkers(CameraRecordingService $recordingService): void
    {
        $nowTs = time();

        foreach ($this->workers as $camId => $worker) {
            // Drain non-blocking stdout/stderr pipes so child processes never block on pipe buffers.
            $out = stream_get_contents($worker['pipes'][1]);
            $err = stream_get_contents($worker['pipes'][2]);
            unset($out, $err);

            $status = proc_get_status($worker['process']);
            $targetBytes = $this->fileSize($worker['target_path']);

            if ($status['running']) {
                // Kill stalled RTSP handshakes that produced zero video after 16 seconds.
                if (($nowTs - $worker['started_ts']) >= 16 && $targetBytes < 1024) {
                    @proc_terminate($worker['process'], 9);
                } else {
                    continue;
                }
            }

            fclose($worker['pipes'][1]);
            fclose($worker['pipes'][2]);
            @proc_close($worker['process']);
            unset($this->workers[$camId]);

            $targetBytes = $this->fileSize($worker['target_path']);
            if ($targetBytes >= CameraRecordingService::MIN_VALID_MP4_BYTES) {
                if ($worker['is_part']) {
                    $this->mergePartIntoRaw($worker['raw_path'], $worker['target_path'], $recordingService->ffmpegBinaryPath());
                }

                $this->failureCount[$camId] = 0;
                $this->backoffUntil[$camId] = $nowTs + 2;
                $this->upsertRecordingRow($worker, CarbonImmutable::now('Asia/Jakarta'));

                continue;
            }

            @unlink($worker['target_path']);
            $failures = ($this->failureCount[$camId] ?? 0) + 1;
            $this->failureCount[$camId] = $failures;
            $this->attemptCursor[$camId] = ($this->attemptCursor[$camId] ?? 0) + 1;

            // Fast 2s retry when flipping TCP -> UDP on the primary URL; exponential backoff afterwards.
            $delay = $failures === 1 ? 2 : min(180, 5 * (2 ** min(5, $failures - 1)));
            $this->backoffUntil[$camId] = $nowTs + $delay;
        }
    }

    private function syncActiveWorkerRows(CarbonImmutable $nowJakarta): void
    {
        foreach ($this->workers as $worker) {
            $totalBytes = $this->fileSize($worker['raw_path'])
                + ($worker['is_part'] ? $this->fileSize($worker['target_path']) : 0);

            if ($totalBytes < CameraRecordingService::MIN_VALID_MP4_BYTES) {
                continue;
            }

            $this->upsertRecordingRow($worker, $nowJakarta);
        }
    }

    /**
     * @param  array{
     *     camera: Camera,
     *     slot_start: CarbonImmutable,
     *     slot_end: CarbonImmutable,
     *     filename: string,
     *     file_path: string,
     *     raw_path: string,
     *     target_path: string,
     *     is_part: bool
     * }  $worker
     */
    private function upsertRecordingRow(array $worker, CarbonImmutable $nowJakarta): void
    {
        $totalBytes = $this->fileSize($worker['raw_path'])
            + ($worker['is_part'] && is_file($worker['target_path']) ? $this->fileSize($worker['target_path']) : 0);

        if ($totalBytes < CameraRecordingService::MIN_VALID_MP4_BYTES) {
            return;
        }

        $elapsed = max(1, min(3600, $nowJakarta->getTimestamp() - $worker['slot_start']->getTimestamp()));

        CameraRecording::query()->updateOrCreate(
            [
                'camera_id' => $worker['camera']->id,
                'started_at' => $worker['slot_start'],
            ],
            [
                'ended_at' => $worker['slot_end'],
                'duration_seconds' => $elapsed,
                'filename' => $worker['filename'],
                'file_path' => $worker['file_path'],
                'size_bytes' => $totalBytes,
                'status' => 'recording',
            ],
        );
    }

    private function mergePartIntoRaw(string $rawPath, string $partPath, string $ffmpeg): void
    {
        if ($this->fileSize($rawPath) < CameraRecordingService::MIN_VALID_MP4_BYTES) {
            @rename($partPath, $rawPath);

            return;
        }

        $listPath = "{$rawPath}.concat.txt";
        $mergedPath = "{$rawPath}.merged.mp4";
        $escapedRaw = str_replace("'", "'\\''", $rawPath);
        $escapedPart = str_replace("'", "'\\''", $partPath);
        @file_put_contents($listPath, "file '{$escapedRaw}'\nfile '{$escapedPart}'\n");

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $proc = @proc_open([
            $ffmpeg,
            '-nostdin',
            '-hide_banner',
            '-loglevel',
            'error',
            '-f',
            'concat',
            '-safe',
            '0',
            '-i',
            $listPath,
            '-c',
            'copy',
            '-movflags',
            '+frag_keyframe+empty_moov+default_base_moof',
            '-y',
            $mergedPath,
        ], $descriptors, $pipes);

        if (is_resource($proc)) {
            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            @proc_close($proc);
        }

        @unlink($listPath);

        if ($this->fileSize($mergedPath) >= $this->fileSize($rawPath)) {
            @rename($mergedPath, $rawPath);
            @unlink($partPath);
        } else {
            @unlink($mergedPath);
        }
    }

    private function terminateAllWorkers(): void
    {
        foreach ($this->workers as $worker) {
            @proc_terminate($worker['process'], 15);
        }

        usleep(400_000);

        foreach ($this->workers as $camId => $worker) {
            $status = proc_get_status($worker['process']);
            if ($status['running']) {
                @proc_terminate($worker['process'], 9);
            }
            fclose($worker['pipes'][1]);
            fclose($worker['pipes'][2]);
            @proc_close($worker['process']);
            unset($this->workers[$camId]);
        }
    }

    private function availableRamKb(): int
    {
        $meminfo = @file_get_contents('/proc/meminfo');
        if ($meminfo !== false && preg_match('/MemAvailable:\s+(\d+)\s+kB/', $meminfo, $matches) === 1) {
            return (int) $matches[1];
        }

        return 1_048_576;
    }

    private function fileSize(string $path): int
    {
        clearstatcache(true, $path);
        if (! is_file($path)) {
            return 0;
        }

        $size = @filesize($path);

        return $size === false ? 0 : $size;
    }
}
