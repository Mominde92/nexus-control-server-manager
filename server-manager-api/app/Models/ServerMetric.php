<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MetricType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single raw metric sample.
 *
 * Append-only and high volume: no SoftDeletes (pruning must be a cheap DELETE),
 * no LogsActivity (auditing a million samples would be absurd), and no
 * `updated_at` — a sample is never modified.
 */
class ServerMetric extends Model
{
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'server_id',
        'metric_type',
        'metric_key',
        'value',
        'recorded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metric_type' => MetricType::class,
            'value' => 'float',
            'recorded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Server, $this>
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeBetween(Builder $query, mixed $from, mixed $to): Builder
    {
        return $query->whereBetween($query->qualifyColumn('recorded_at'), [$from, $to]);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeOfType(Builder $query, MetricType|string $type): Builder
    {
        return $query->where(
            $query->qualifyColumn('metric_type'),
            $type instanceof MetricType ? $type->value : $type,
        );
    }
}
