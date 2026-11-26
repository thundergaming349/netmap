<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $device_id
 * @property string $name
 * @property string $ip
 * @property string $brand
 * @property string|null $web_url
 * @property int $channel_count
 * @property int $capacity_bytes
 * @property int $used_bytes
 * @property int $retention_days
 * @property string $status
 * @property CarbonImmutable|null $last_ok_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'device_id',
    'name',
    'ip',
    'brand',
    'web_url',
    'channel_count',
    'capacity_bytes',
    'used_bytes',
    'retention_days',
    'status',
    'last_ok_at',
])]
final class Nvr extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel_count' => 'integer',
            'capacity_bytes' => 'integer',
            'used_bytes' => 'integer',
            'retention_days' => 'integer',
            'last_ok_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * @return HasMany<Camera, $this>
     */
    public function cameras(): HasMany
    {
        return $this->hasMany(Camera::class);
    }
}
