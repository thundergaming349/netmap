<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $segment_id
 * @property string $ip_start
 * @property string $ip_end
 * @property int $start_octet
 * @property int $end_octet
 * @property string $label
 * @property string|null $description
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'segment_id',
    'ip_start',
    'ip_end',
    'start_octet',
    'end_octet',
    'label',
    'description',
    'created_by',
])]
final class IpReservation extends Model
{
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_octet' => 'integer',
            'end_octet' => 'integer',
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
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function containsOctet(int $octet): bool
    {
        return $octet >= $this->start_octet && $octet <= $this->end_octet;
    }
}
