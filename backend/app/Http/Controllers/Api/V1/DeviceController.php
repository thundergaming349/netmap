<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Devices\CreateDevice;
use App\Actions\Devices\DeleteDevice;
use App\Actions\Devices\TraceDevicePath;
use App\Actions\Devices\UpdateDevice;
use App\Enums\DeviceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\StoreDeviceRequest;
use App\Http\Requests\V1\UpdateDeviceRequest;
use App\Http\Resources\V1\DeviceResource;
use App\Models\Device;
use App\Models\Segment;
use App\Support\NetworkFormat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DeviceController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $devices = Device::query()
            ->with(['segment', 'networkSwitch'])
            ->filter($request->only(['segment_id', 'type', 'status', 'q']))
            ->orderByIp()
            ->paginate($request->integer('per_page', 100));

        return DeviceResource::collection($devices);
    }

    public function show(Device $device): DeviceResource
    {
        return DeviceResource::make($device->load(['segment', 'networkSwitch', 'camera']));
    }

    public function store(StoreDeviceRequest $request, CreateDevice $createDevice): JsonResponse
    {
        $device = $createDevice->handle($request->validated(), $request->user());

        return DeviceResource::make($device)
            ->additional(['message' => 'Perangkat disimpan.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateDeviceRequest $request, Device $device, UpdateDevice $updateDevice): DeviceResource
    {
        $updated = $updateDevice->handle($device, $request->validated(), $request->user());

        return DeviceResource::make($updated)->additional(['message' => 'Perangkat diperbarui.']);
    }

    public function destroy(Request $request, Device $device, DeleteDevice $deleteDevice): JsonResponse
    {
        $deleteDevice->handle($device, $request->user());

        return response()->json(['message' => 'Perangkat dihapus.']);
    }

    public function trace(Device $device, TraceDevicePath $traceDevicePath): JsonResponse
    {
        return response()->json([
            'data' => $traceDevicePath->handle($device),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $devices = Device::query()
            ->with(['segment', 'networkSwitch'])
            ->filter($request->only(['segment_id', 'type', 'status', 'q']))
            ->orderByIp()
            ->get();

        return response()->streamDownload(function () use ($devices): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fputcsv($out, ['name', 'type', 'segment', 'ip', 'mac', 'location', 'switch', 'port', 'status', 'note']);
            foreach ($devices as $device) {
                fputcsv($out, [
                    $device->name,
                    $device->type->value,
                    $device->segment !== null ? $device->segment->name : '',
                    $device->ip,
                    strtoupper((string) $device->mac),
                    $device->location ?? '',
                    $device->networkSwitch !== null ? $device->networkSwitch->name : '',
                    $device->port ?? '',
                    $device->status->value,
                    $device->note ?? '',
                ]);
            }
            fclose($out);
        }, 'netmap-perangkat.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function import(Request $request, CreateDevice $createDevice): JsonResponse
    {
        $request->validate([
            'csv' => ['required', 'string'],
        ], [
            'csv.required' => 'Data CSV wajib diisi.',
        ]);

        $lines = preg_split('/\r\n|\r|\n/', trim((string) $request->input('csv'))) ?: [];
        $imported = 0;
        $skipped = 0;

        foreach ($lines as $index => $line) {
            if (trim($line) === '') {
                continue;
            }
            $cols = str_getcsv($line);
            if ($index === 0 && isset($cols[0]) && strtolower(trim((string) $cols[0])) === 'name') {
                continue;
            }

            $name = trim((string) ($cols[0] ?? ''));
            $typeRaw = strtolower(trim((string) ($cols[1] ?? 'other')));
            $ip = trim((string) ($cols[3] ?? $cols[2] ?? ''));
            $mac = NetworkFormat::normalizeMac($cols[4] ?? $cols[3] ?? null);

            if ($name === '' || ! NetworkFormat::isValidIpv4NoLeadingZeros($ip)) {
                $skipped++;

                continue;
            }

            $segment = Segment::query()->get()->first(fn (Segment $s) => $s->containsIp($ip));
            if ($segment === null) {
                $skipped++;

                continue;
            }

            $type = DeviceType::tryFrom($typeRaw) ?? DeviceType::Other;

            try {
                $createDevice->handle([
                    'name' => $name,
                    'type' => $type->value,
                    'segment_id' => $segment->id,
                    'ip' => $ip,
                    'mac' => NetworkFormat::isValidMac($mac) ? $mac : null,
                    'location' => $cols[5] ?? null,
                    'port' => $cols[7] ?? null,
                ], $request->user());
                $imported++;
            } catch (\Throwable) {
                $skipped++;
            }
        }

        return response()->json([
            'data' => [
                'imported' => $imported,
                'skipped' => $skipped,
            ],
            'message' => "{$imported} perangkat berhasil diimpor".($skipped > 0 ? " ({$skipped} baris dilewati)." : '.'),
        ], 202);
    }
}
