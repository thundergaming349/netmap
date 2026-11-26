<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Support\NetworkFormat;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

final class MikrotikWriteActionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('ip')) {
            $this->merge(['ip' => trim((string) $this->input('ip'))]);
        }
        if ($this->has('mac')) {
            $this->merge(['mac' => NetworkFormat::normalizeMac((string) $this->input('mac'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'segment_id' => ['nullable', 'integer', 'exists:segments,id'],
            'ip' => [
                'nullable',
                'string',
                function (string $attr, mixed $value, Closure $fail): void {
                    if ($value !== null && $value !== '' && ! NetworkFormat::isValidIpv4NoLeadingZeros((string) $value)) {
                        $fail('Alamat IPv4 tidak valid.');
                    }
                },
            ],
            'mac' => [
                'required',
                'string',
                function (string $attr, mixed $value, Closure $fail): void {
                    if (! NetworkFormat::isValidMac((string) $value)) {
                        $fail('Alamat MAC tidak valid.');
                    }
                },
            ],
            'comment' => ['nullable', 'string', 'max:120'],
            'reason' => ['nullable', 'string', 'max:200'],
        ];
    }
}
