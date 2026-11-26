<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeviceStatus;
use App\Enums\DeviceType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $segment_id
 * @property int|null $network_switch_id
 * @property string $name
 * @property DeviceType $type
 * @property DeviceStatus $status
 * @property string $ip
 * @property string|null $mac
 * @property string|null $location
 * @property string|null $port
 * @property string|null $note
 * @property bool $monitor_ping
 * @property bool $is_static_lease
 * @property bool $is_arp_pinned
 * @property CarbonImmutable|null $last_seen_at
 * @property int|null $latency_ms
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'segment_id',
    'network_switch_id',
    'name',
    'type',
    'status',
    'ip',
    'mac',
    'location',
    'port',
    'note',
    'monitor_ping',
    'is_static_lease',
    'is_arp_pinned',
    'last_seen_at',
    'latency_ms',
    'meta',
])]
final class Device extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DeviceType::class,
            'status' => DeviceStatus::class,
            'monitor_ping' => 'boolean',
            'is_static_lease' => 'boolean',
            'is_arp_pinned' => 'boolean',
            'last_seen_at' => 'immutable_datetime',
            'latency_ms' => 'integer',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Segment, $this>
     */
    public function segment(): BelongsTo
    {
        return $this->belongsTo(Segment::class);
    }

    /**
     * @return BelongsTo<NetworkSwitch, $this>
     */
    public function networkSwitch(): BelongsTo
    {
        return $this->belongsTo(NetworkSwitch::class);
    }

    /**
     * @return HasOne<Camera, $this>
     */
    public function camera(): HasOne
    {
        return $this->hasOne(Camera::class);
    }

    /**
     * @return HasOne<Nvr, $this>
     */
    public function nvr(): HasOne
    {
        return $this->hasOne(Nvr::class);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeInSegment(Builder $query, int $segmentId): void
    {
        $query->where('segment_id', $segmentId);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOrderByIp(Builder $query): void
    {
        $query->orderByRaw('ip ASC');
    }

    /**
     * @param  Builder<self>  $query
     * @param  array<string, mixed>  $filters
     */
    public function scopeFilter(Builder $query, array $filters): void
    {
        if (! empty($filters['segment_id'])) {
            $query->where('segment_id', (int) $filters['segment_id']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', (string) $filters['type']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }

        if (! empty($filters['q'])) {
            $search = '%'.strtolower(trim((string) $filters['q'])).'%';
            $query->where(function (Builder $sub) use ($search): void {
                $sub->whereRaw('LOWER(name) LIKE ?', [$search])
                    ->orWhereRaw('CAST(ip AS TEXT) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(CAST(mac AS TEXT)) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(COALESCE(location, \'\')) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(COALESCE(port, \'\')) LIKE ?', [$search]);
            });
        }
    }
}
