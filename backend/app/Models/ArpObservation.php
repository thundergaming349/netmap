<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $segment_id
 * @property string $ip
 * @property string $mac
 * @property string|null $interface
 * @property bool $is_dynamic
 * @property bool $is_complete
 * @property CarbonImmutable $first_seen_at
 * @property CarbonImmutable $last_seen_at
 */
#[Fillable([
    'segment_id',
    'ip',
    'mac',
    'interface',
    'is_dynamic',
    'is_complete',
    'first_seen_at',
    'last_seen_at',
])]
final class ArpObservation extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_dynamic' => 'boolean',
            'is_complete' => 'boolean',
            'first_seen_at' => 'immutable_datetime',
            'last_seen_at' => 'immutable_datetime',
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
     * @param  Builder<self>  $query
     */
    public function scopeRecent(Builder $query, int $minutes = 10): void
    {
        $query->where('last_seen_at', '>=', now()->subMinutes($minutes));
    }
}
