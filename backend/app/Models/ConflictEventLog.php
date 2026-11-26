<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $conflict_event_id
 * @property int|null $actor_id
 * @property CarbonImmutable $logged_at
 * @property string $message
 * @property array<string, mixed>|null $meta
 */
#[Fillable([
    'conflict_event_id',
    'actor_id',
    'logged_at',
    'message',
    'meta',
])]
final class ConflictEventLog extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'logged_at' => 'immutable_datetime',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<ConflictEvent, $this>
     */
    public function conflictEvent(): BelongsTo
    {
        return $this->belongsTo(ConflictEvent::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
