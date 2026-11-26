<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use App\Support\NetworkFormat;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

final class CheckIpRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'ip' => trim((string) $this->query('ip', $this->input('ip', ''))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ip' => [
                'required',
                'string',
                function (string $attr, mixed $value, Closure $fail): void {
                    if (! NetworkFormat::isValidIpv4NoLeadingZeros((string) $value)) {
                        $fail('Masukkan alamat IPv4 yang benar (contoh: 192.168.20.57).');
                    }
                },
            ],
            'segment_id' => ['nullable', 'integer', 'exists:segments,id'],
        ];
    }
}
