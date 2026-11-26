<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $router_id
 * @property string $name
 * @property string $slug
 * @property int|null $vlan_id
 * @property string $subnet
 * @property string $gateway
 * @property string $interface
 * @property string|null $pool_start
 * @property string|null $pool_end
 * @property int $static_start
 * @property int $static_end
 * @property string|null $egress
 * @property string $color
 * @property bool $is_isolated
 * @property string|null $bandwidth_limit
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'router_id',
    'name',
    'slug',
    'vlan_id',
    'subnet',
    'gateway',
    'interface',
    'pool_start',
    'pool_end',
    'static_start',
    'static_end',
    'egress',
    'color',
    'is_isolated',
    'bandwidth_limit',
    'notes',
])]
final class Segment extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'vlan_id' => 'integer',
            'static_start' => 'integer',
            'static_end' => 'integer',
            'is_isolated' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Router, $this>
     */
    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }

    /**
     * @return HasMany<Device, $this>
     */
    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    /**
     * @return HasMany<IpReservation, $this>
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(IpReservation::class);
    }

    /**
     * @return HasMany<ArpObservation, $this>
     */
    public function arpObservations(): HasMany
    {
        return $this->hasMany(ArpObservation::class);
    }

    /**
     * @return HasMany<DhcpLease, $this>
     */
    public function dhcpLeases(): HasMany
    {
        return $this->hasMany(DhcpLease::class);
    }

    /**
     * @return HasMany<ConflictEvent, $this>
     */
    public function conflictEvents(): HasMany
    {
        return $this->hasMany(ConflictEvent::class);
    }

    /**
     * @return HasMany<NetworkSwitch, $this>
     */
    public function switches(): HasMany
    {
        return $this->hasMany(NetworkSwitch::class);
    }

    /**
     * Base prefix (first 3 octets) of the /24 subnet, e.g. "192.168.20".
     */
    public function networkPrefix(): string
    {
        $network = explode('/', $this->subnet)[0];
        $parts = explode('.', $network);

        return implode('.', array_slice($parts, 0, 3));
    }

    public function ipForOctet(int $octet): string
    {
        return $this->networkPrefix().'.'.$octet;
    }

    public function octetOf(string $ip): ?int
    {
        $prefix = $this->networkPrefix().'.';
        if (! str_starts_with($ip, $prefix)) {
            return null;
        }

        $last = substr($ip, strlen($prefix));
        if (! ctype_digit($last)) {
            return null;
        }

        $val = (int) $last;

        return ($val >= 0 && $val <= 255) ? $val : null;
    }

    public function containsIp(string $ip): bool
    {
        return $this->octetOf($ip) !== null;
    }

    public function isInPool(string $ip): bool
    {
        if ($this->pool_start === null || $this->pool_end === null) {
            return false;
        }

        $octet = $this->octetOf($ip);
        $start = $this->octetOf($this->pool_start);
        $end = $this->octetOf($this->pool_end);

        if ($octet === null || $start === null || $end === null) {
            return false;
        }

        return $octet >= $start && $octet <= $end;
    }
}
