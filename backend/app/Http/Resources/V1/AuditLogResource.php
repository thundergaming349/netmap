<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AuditLog
 */
final class AuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'target' => $this->target,
            'ip' => $this->ip,
            'user' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name ?? $this->user->email,
                'email' => $this->user->email,
            ] : null,
            'before' => $this->before,
            'after' => $this->after,
            'meta' => $this->meta,
            'created_at' => $this->created_at?->toIso8601ZuluString(),
        ];
    }
}
