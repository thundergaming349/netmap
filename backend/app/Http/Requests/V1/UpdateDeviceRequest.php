<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Enums\DeviceType;
use App\Models\Device;
use App\Models\Segment;
use App\Support\NetworkFormat;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateDeviceRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('ip')) {
            $this->merge(['ip' => trim((string) $this->input('ip'))]);
        }
        if ($this->has('mac')) {
            $this->merge(['mac' => NetworkFormat::normalizeMac($this->filled('mac') ? (string) $this->input('mac') : null)]);
        }
        if ($this->has('name')) {
            $this->merge(['name' => trim((string) $this->input('name'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'type' => ['sometimes', Rule::enum(DeviceType::class)],
            'segment_id' => ['sometimes', 'integer', 'exists:segments,id'],
            'network_switch_id' => ['nullable', 'integer', 'exists:network_switches,id'],
            'ip' => [
                'sometimes',
                'required',
                'string',
                function (string $attr, mixed $value, Closure $fail): void {
                    $ip = (string) $value;
                    if (! NetworkFormat::isValidIpv4NoLeadingZeros($ip)) {
                        $fail('Format IPv4 tidak valid (tanpa angka nol di depan).');

                        return;
                    }

                    /** @var Device|null $device */
                    $device = $this->route('device');
                    $segmentId = (int) ($this->input('segment_id') ?? $device?->segment_id);
                    $segment = Segment::query()->find($segmentId);
                    if ($segment !== null && ! $segment->containsIp($ip)) {
                        $fail("IP {$ip} tidak berada di dalam subnet jalur {$segment->name} ({$segment->subnet}).");
                    }
                },
            ],
            'mac' => [
                'nullable',
                'string',
                function (string $attr, mixed $value, Closure $fail): void {
                    if ($value !== null && $value !== '' && ! NetworkFormat::isValidMac((string) $value)) {
                        $fail('Format alamat MAC tidak valid.');
                    }
                },
            ],
            'location' => ['nullable', 'string', 'max:120'],
            'port' => ['nullable', 'string', 'max:64'],
            'note' => ['nullable', 'string', 'max:500'],
            'monitor_ping' => ['sometimes', 'boolean'],
            'is_static_lease' => ['sometimes', 'boolean'],
            'is_arp_pinned' => ['sometimes', 'boolean'],
        ];
    }
}
