<?php

declare(strict_types=1);

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

final class StoreReservationRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'segment_id' => ['required', 'integer', 'exists:segments,id'],
            'start_octet' => ['required', 'integer', 'min:2', 'max:254'],
            'end_octet' => ['required', 'integer', 'min:2', 'max:254', 'gte:start_octet'],
            'label' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'segment_id.required' => 'Pilih jalur jaringan.',
            'start_octet.required' => 'Oktet awal wajib diisi.',
            'end_octet.required' => 'Oktet akhir wajib diisi.',
            'end_octet.gte' => 'Oktet akhir harus lebih besar atau sama dengan oktet awal.',
            'label.required' => 'Nama keperluan reservasi wajib diisi.',
        ];
    }
}
