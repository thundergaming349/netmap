<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $host
 * @property int $api_port
 * @property bool $use_ssl
 * @property string|null $webhook_token
 * @property string|null $version
 * @property string|null $uptime
 * @property int|null $cpu_load
 * @property CarbonImmutable|null $last_synced_at
 * @property string|null $last_error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name',
    'host',
    'api_port',
    'use_ssl',
    'webhook_token',
    'version',
    'uptime',
    'cpu_load',
    'last_synced_at',
    'last_error',
])]
final class Router extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'api_port' => 'integer',
            'use_ssl' => 'boolean',
            'cpu_load' => 'integer',
            'last_synced_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return HasMany<Segment, $this>
     */
    public function segments(): HasMany
    {
        return $this->hasMany(Segment::class);
    }

    /**
     * @return HasMany<Uplink, $this>
     */
    public function uplinks(): HasMany
    {
        return $this->hasMany(Uplink::class);
    }

    /**
     * @return HasMany<RouterRoute, $this>
     */
    public function routes(): HasMany
    {
        return $this->hasMany(RouterRoute::class);
    }
}
