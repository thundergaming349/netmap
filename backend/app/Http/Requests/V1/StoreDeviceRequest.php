<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Enums\DeviceType;
use App\Models\Segment;
use App\Support\NetworkFormat;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreDeviceRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'ip' => trim((string) $this->input('ip')),
            'mac' => NetworkFormat::normalizeMac($this->filled('mac') ? (string) $this->input('mac') : null),
            'name' => trim((string) $this->input('name')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::enum(DeviceType::class)],
            'segment_id' => ['required', 'integer', 'exists:segments,id'],
            'network_switch_id' => ['nullable', 'integer', 'exists:network_switches,id'],
            'ip' => [
                'required',
                'string',
                function (string $attr, mixed $value, Closure $fail): void {
                    $ip = (string) $value;
                    if (! NetworkFormat::isValidIpv4NoLeadingZeros($ip)) {
                        $fail('Format IPv4 tidak valid (tanpa angka nol di depan).');

                        return;
                    }

                    $segmentId = (int) $this->input('segment_id');
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
                        $fail('Format alamat MAC tidak valid (contoh: BC:32:5F:1A:0C:31).');
                    }
                },
            ],
            'location' => ['nullable', 'string', 'max:120'],
            'port' => ['nullable', 'string', 'max:64'],
            'note' => ['nullable', 'string', 'max:500'],
            'monitor_ping' => ['sometimes', 'boolean'],
            'is_static_lease' => ['sometimes', 'boolean'],
            'is_arp_pinned' => ['sometimes', 'boolean'],
            'nvr_id' => ['nullable', 'integer', 'exists:nvrs,id'],
            'channel' => ['nullable', 'integer', 'min:1', 'max:128'],
            'brand' => ['nullable', 'string', 'max:32'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama perangkat wajib diisi.',
            'type.required' => 'Pilih tipe perangkat.',
            'segment_id.required' => 'Pilih jalur jaringan.',
            'ip.required' => 'Alamat IP wajib diisi.',
        ];
    }
}
