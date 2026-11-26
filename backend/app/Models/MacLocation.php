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
 * @property string $mac
 * @property int|null $network_switch_id
 * @property string|null $interface
 * @property string|null $port
 * @property string $source
 * @property CarbonImmutable $last_seen_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'mac',
    'network_switch_id',
    'interface',
    'port',
    'source',
    'last_seen_at',
])]
final class MacLocation extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_seen_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<NetworkSwitch, $this>
     */
    public function networkSwitch(): BelongsTo
    {
        return $this->belongsTo(NetworkSwitch::class);
    }
}
