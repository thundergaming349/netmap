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
 * @property int|null $router_id
 * @property string $name
 * @property string|null $provider
 * @property string $interface
 * @property string $gateway
 * @property string $check_target
 * @property int $distance
 * @property string $status
 * @property int|null $latency_ms
 * @property int $rx_mbps
 * @property int $tx_mbps
 * @property CarbonImmutable|null $last_changed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'router_id',
    'name',
    'provider',
    'interface',
    'gateway',
    'check_target',
    'distance',
    'status',
    'latency_ms',
    'rx_mbps',
    'tx_mbps',
    'last_changed_at',
])]
final class Uplink extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'distance' => 'integer',
            'latency_ms' => 'integer',
            'rx_mbps' => 'integer',
            'tx_mbps' => 'integer',
            'last_changed_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Router, $this>
     */
    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }
}
