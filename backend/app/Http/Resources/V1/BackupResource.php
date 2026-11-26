<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\Backup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Backup
 */
final class BackupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'filename' => $this->filename,
            'size_bytes' => $this->size_bytes,
            'includes_mikrotik_export' => $this->includes_mikrotik_export,
            'status' => $this->status,
            'created_by_name' => $this->creator ? ($this->creator->name ?? $this->creator->email) : 'Jadwal Harian (02:00)',
            'created_at' => $this->created_at->toIso8601ZuluString(),
        ];
    }
}
