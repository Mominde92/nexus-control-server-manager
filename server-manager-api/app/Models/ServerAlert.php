<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AlertSeverity;
use App\Enums\MetricType;
use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A stateful alert incident.
 *
 * One row per *condition*, not per breaching sample: repeated breaches bump
 * `occurrences` and `last_observed_at` on the existing open row. That is what
 * keeps a flapping host from flooding the table.
 */
class ServerAlert extends Model
{
    use Searchable;

    public const TYPE_METRIC_THRESHOLD = 'metric_threshold';

    public const TYPE_OFFLINE = 'offline';

    public const TYPE_AGENT_ERROR = 'agent_error';

    public const SEARCHABLE = ['title', 'message', 'fingerprint'];

    public const SEARCHABLE_RELATIONS = ['server' => ['name', 'hostname']];

    public const SORTABLE = ['started_at', 'severity', 'resolved_at', 'last_observed_at', 'id'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'server_id',
        'severity',
        'type',
        'fingerprint',
        'title',
        'message',
        'metric_type',
        'observed_value',
        'threshold_value',
        'started_at',
        'last_observed_at',
        'resolved_at',
        'occurrences',
        'acknowledged_at',
        'acknowledged_by',
        'notified_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'severity' => AlertSeverity::class,
            'metric_type' => MetricType::class,
            'observed_value' => 'float',
            'threshold_value' => 'float',
            'started_at' => 'datetime',
            'last_observed_at' => 'datetime',
            'resolved_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'notified_at' => 'datetime',
            'occurrences' => 'integer',
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
     * @return BelongsTo<User, $this>
     */
    public function acknowledger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function isOpen(): bool
    {
        return $this->resolved_at === null;
    }

    public function isAcknowledged(): bool
    {
        return $this->acknowledged_at !== null;
    }

    /**
     * Stable identity for a condition, used to dedupe open incidents.
     */
    public static function fingerprintFor(string $type, ?string $metric, ?string $level = null): string
    {
        return implode(':', array_filter([$type, $metric, $level]));
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull($query->qualifyColumn('resolved_at'));
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeResolved(Builder $query): Builder
    {
        return $query->whereNotNull($query->qualifyColumn('resolved_at'));
    }
}
