<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $filename
 * @property int $size_bytes
 * @property bool $includes_mikrotik_export
 * @property string $status
 * @property int|null $created_by
 * @property CarbonImmutable $created_at
 */
#[Fillable([
    'filename',
    'size_bytes',
    'includes_mikrotik_export',
    'status',
    'created_by',
    'created_at',
])]
final class Backup extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'includes_mikrotik_export' => 'boolean',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
