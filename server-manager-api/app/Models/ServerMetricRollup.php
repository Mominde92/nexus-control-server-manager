<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MetricType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A pre-aggregated metric bucket (hourly or daily).
 */
class ServerMetricRollup extends Model
{
    public const PERIOD_HOUR = 'hour';

    public const PERIOD_DAY = 'day';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'server_id',
        'metric_type',
        'metric_key',
        'period',
        'bucket_start',
        'avg_value',
        'min_value',
        'max_value',
        'samples',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metric_type' => MetricType::class,
            'bucket_start' => 'datetime',
            'avg_value' => 'float',
            'min_value' => 'float',
            'max_value' => 'float',
            'samples' => 'integer',
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
    public function scopePeriod(Builder $query, string $period): Builder
    {
        return $query->where($query->qualifyColumn('period'), $period);
    }
}
