<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $segment_id
 * @property string $name
 * @property string|null $ip
 * @property string|null $mac
 * @property string|null $uplink_port
 * @property int $total_ports
 * @property bool $is_managed
 * @property string|null $snmp_community
 * @property string|null $location
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'segment_id',
    'name',
    'ip',
    'mac',
    'uplink_port',
    'total_ports',
    'is_managed',
    'snmp_community',
    'location',
])]
#[Hidden(['snmp_community'])]
final class NetworkSwitch extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_ports' => 'integer',
            'is_managed' => 'boolean',
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
     * @return HasMany<Device, $this>
     */
    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }
}
