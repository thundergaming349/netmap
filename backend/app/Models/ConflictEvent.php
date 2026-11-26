<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConflictStatus;
use App\Enums\ConflictType;
use App\Enums\Severity;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property ConflictType $type
 * @property Severity $severity
 * @property ConflictStatus $status
 * @property int $segment_id
 * @property string $ip
 * @property list<string> $macs
 * @property string $title
 * @property string $summary
 * @property string|null $suggestion
 * @property string $source
 * @property int|null $device_id
 * @property CarbonImmutable $opened_at
 * @property CarbonImmutable $last_detected_at
 * @property CarbonImmutable|null $resolved_at
 * @property int|null $resolved_by
 * @property string|null $resolution_note
 * @property CarbonImmutable|null $notified_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'type',
    'severity',
    'status',
    'segment_id',
    'ip',
    'macs',
    'title',
    'summary',
    'suggestion',
    'source',
    'device_id',
    'opened_at',
    'last_detected_at',
    'resolved_at',
    'resolved_by',
    'resolution_note',
    'notified_at',
])]
final class ConflictEvent extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ConflictType::class,
            'severity' => Severity::class,
            'status' => ConflictStatus::class,
            'macs' => 'array',
            'opened_at' => 'immutable_datetime',
            'last_detected_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
            'notified_at' => 'immutable_datetime',
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
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * @return HasMany<ConflictEventLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(ConflictEventLog::class)->orderBy('logged_at');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', ConflictStatus::Active->value);
    }
}
