<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $router_id
 * @property string $dst_address
 * @property string $gateway
 * @property string $routing_table
 * @property int $distance
 * @property int|null $scope
 * @property bool $is_active
 * @property bool $is_dynamic
 * @property bool $is_present
 * @property string|null $comment
 * @property array<int>|null $segment_ids
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'router_id',
    'dst_address',
    'gateway',
    'routing_table',
    'distance',
    'scope',
    'is_active',
    'is_dynamic',
    'is_present',
    'comment',
    'segment_ids',
])]
final class RouterRoute extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'distance' => 'integer',
            'scope' => 'integer',
            'is_active' => 'boolean',
            'is_dynamic' => 'boolean',
            'is_present' => 'boolean',
            'segment_ids' => 'array',
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
