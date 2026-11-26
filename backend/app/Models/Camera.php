<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CameraStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $device_id
 * @property int|null $nvr_id
 * @property int $channel
 * @property string $brand
 * @property string $location_group
 * @property string $stream_key
 * @property string|null $stream_main
 * @property string|null $stream_sub
 * @property CameraStatus $status
 * @property int $fps
 * @property string $resolution
 * @property CarbonImmutable|null $last_ok_at
 * @property CarbonImmutable|null $last_snapshot_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'device_id',
    'nvr_id',
    'channel',
    'brand',
    'location_group',
    'stream_key',
    'stream_main',
    'stream_sub',
    'status',
    'fps',
    'resolution',
    'last_ok_at',
    'last_snapshot_at',
])]
final class Camera extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => 'integer',
            'status' => CameraStatus::class,
            'fps' => 'integer',
            'last_ok_at' => 'immutable_datetime',
            'last_snapshot_at' => 'immutable_datetime',
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
     * @return BelongsTo<Nvr, $this>
     */
    public function nvr(): BelongsTo
    {
        return $this->belongsTo(Nvr::class);
    }
}
