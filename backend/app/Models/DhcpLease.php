<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $segment_id
 * @property string $ip
 * @property string $mac
 * @property string|null $host_name
 * @property string|null $server
 * @property string $status
 * @property bool $is_dynamic
 * @property bool $is_present
 * @property string|null $routeros_id
 * @property string|null $comment
 * @property CarbonImmutable $last_seen_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'segment_id',
    'ip',
    'mac',
    'host_name',
    'server',
    'status',
    'is_dynamic',
    'is_present',
    'routeros_id',
    'comment',
    'last_seen_at',
])]
final class DhcpLease extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_dynamic' => 'boolean',
            'is_present' => 'boolean',
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
}
