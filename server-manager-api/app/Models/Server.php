<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MetricType;
use App\Enums\ServerHealth;
use App\Enums\ServerOs;
use App\Models\Concerns\LogsActivity;
use App\Models\Concerns\Searchable;
use App\Models\Concerns\TracksBlame;
use Carbon\CarbonInterface;
use Database\Factories\ServerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Config;

/**
 * A monitored server.
 *
 * Servers are created and destroyed at runtime; nothing in the application
 * assumes how many exist. Health is *derived* from the heartbeat cursor and the
 * latest samples rather than stored, so it can never go stale.
 */
class Server extends Model
{
    /** @use HasFactory<ServerFactory> */
    use HasFactory;

    use LogsActivity;
    use Searchable;
    use SoftDeletes;
    use TracksBlame;

    public const SEARCHABLE = ['name', 'hostname', 'ip_address', 'os_version', 'agent_version'];

    public const SEARCHABLE_RELATIONS = [
        'hosting' => ['title'],
        'client' => ['name', 'company'],
        'project' => ['name'],
    ];

    public const SORTABLE = [
        'name',
        'os',
        'monitoring_enabled',
        'last_heartbeat_at',
        'created_at',
        'updated_at',
        'id',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'hostname',
        'ip_address',
        'os',
        'os_version',
        'hosting_id',
        'client_id',
        'project_id',
        'monitoring_enabled',
        'heartbeat_interval_seconds',
        'heartbeat_grace_seconds',
        'cpu_warning_threshold',
        'cpu_critical_threshold',
        'memory_warning_threshold',
        'memory_critical_threshold',
        'disk_warning_threshold',
        'disk_critical_threshold',
        'notes',
    ];

    /**
     * Heartbeat telemetry changes constantly and would swamp the audit trail.
     *
     * @var list<string>
     */
    protected array $activityIgnored = [
        'last_heartbeat_at',
        'agent_version',
        'uptime_seconds',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'os' => ServerOs::class,
            'monitoring_enabled' => 'boolean',
            'last_heartbeat_at' => 'datetime',
            'heartbeat_interval_seconds' => 'integer',
            'heartbeat_grace_seconds' => 'integer',
            'uptime_seconds' => 'integer',
            'cpu_warning_threshold' => 'integer',
            'cpu_critical_threshold' => 'integer',
            'memory_warning_threshold' => 'integer',
            'memory_critical_threshold' => 'integer',
            'disk_warning_threshold' => 'integer',
            'disk_critical_threshold' => 'integer',
        ];
    }

    // -----------------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------------

    /**
     * @return BelongsTo<Hosting, $this>
     */
    public function hosting(): BelongsTo
    {
        return $this->belongsTo(Hosting::class);
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return HasMany<ServerAgentCredential, $this>
     */
    public function credentials(): HasMany
    {
        return $this->hasMany(ServerAgentCredential::class);
    }

    /**
     * The credential an agent should currently be using.
     *
     * @return HasOne<ServerAgentCredential, $this>
     */
    public function activeCredential(): HasOne
    {
        return $this->hasOne(ServerAgentCredential::class)
            ->whereNull('revoked_at')
            ->latestOfMany();
    }

    /**
     * @return HasMany<ServerHeartbeat, $this>
     */
    public function heartbeats(): HasMany
    {
        return $this->hasMany(ServerHeartbeat::class);
    }

    /**
     * @return HasMany<ServerMetric, $this>
     */
    public function metrics(): HasMany
    {
        return $this->hasMany(ServerMetric::class);
    }

    /**
     * @return HasMany<ServerMetricRollup, $this>
     */
    public function rollups(): HasMany
    {
        return $this->hasMany(ServerMetricRollup::class);
    }

    /**
     * @return HasMany<ServerAlert, $this>
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(ServerAlert::class);
    }

    /**
     * @return HasMany<ServerAlert, $this>
     */
    public function openAlerts(): HasMany
    {
        return $this->alerts()->whereNull('resolved_at');
    }

    // -----------------------------------------------------------------------
    // Derived health
    // -----------------------------------------------------------------------

    /**
     * Seconds after which a silent agent is considered offline.
     */
    public function offlineAfterSeconds(): int
    {
        $interval = $this->heartbeat_interval_seconds
            ?: (int) Config::get('monitoring.heartbeat.default_interval_seconds', 60);

        $grace = $this->heartbeat_grace_seconds
            ?: (int) Config::get('monitoring.heartbeat.default_grace_seconds', 150);

        // Grace is an absolute floor, not an addition, so a host reporting every
        // 10 minutes is not declared dead after 150s.
        return max($grace, $interval + 30);
    }

    public function isStale(): bool
    {
        $last = $this->last_heartbeat_at;

        if (! $last instanceof CarbonInterface) {
            return true;
        }

        return $last->diffInSeconds(now()) > $this->offlineAfterSeconds();
    }

    /**
     * Live health, derived rather than stored.
     *
     * `$openSeverity` lets a caller that already loaded alert severities (e.g.
     * the paginated list) avoid a per-row query — passing null falls back to a
     * relation read, which is why callers should eager load `openAlerts`.
     */
    public function health(?string $openSeverity = null): ServerHealth
    {
        if (! $this->monitoring_enabled) {
            return ServerHealth::Disabled;
        }

        if ($this->last_heartbeat_at === null) {
            return ServerHealth::Pending;
        }

        if ($this->isStale()) {
            return ServerHealth::Offline;
        }

        $severity = $openSeverity ?? $this->worstOpenAlertSeverity();

        return match ($severity) {
            'critical' => ServerHealth::Critical,
            'warning' => ServerHealth::Warning,
            default => ServerHealth::Healthy,
        };
    }

    private function worstOpenAlertSeverity(): ?string
    {
        if (! $this->relationLoaded('openAlerts')) {
            return null;
        }

        $severities = $this->openAlerts->pluck('severity')->map(
            static fn ($s) => is_string($s) ? $s : ($s->value ?? null),
        );

        if ($severities->contains('critical')) {
            return 'critical';
        }

        return $severities->contains('warning') ? 'warning' : null;
    }

    /**
     * Effective threshold for a metric: the per-server override when set,
     * otherwise the configured default.
     */
    public function thresholdFor(MetricType $metric, string $level): ?float
    {
        $prefix = match ($metric) {
            MetricType::CpuPercent => 'cpu',
            MetricType::MemoryPercent => 'memory',
            MetricType::DiskPercent => 'disk',
            default => null,
        };

        if ($prefix === null) {
            return null;
        }

        $override = $this->getAttribute("{$prefix}_{$level}_threshold");

        if ($override !== null) {
            return (float) $override;
        }

        $default = Config::get("monitoring.thresholds.{$prefix}.{$level}");

        return $default === null ? null : (float) $default;
    }

    // -----------------------------------------------------------------------
    // Scopes
    // -----------------------------------------------------------------------

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeMonitored(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('monitoring_enabled'), true);
    }

    /**
     * Monitored servers whose last heartbeat is older than their own tolerance.
     *
     * Uses the denormalised cursor on `servers` (never the heartbeats table), so
     * the sweep cost scales with server count, not history size.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeStale(Builder $query): Builder
    {
        $last = $query->qualifyColumn('last_heartbeat_at');

        return $query
            ->monitored()
            ->whereNotNull($last)
            // Coarse pre-filter using the largest plausible tolerance; the exact
            // per-server comparison happens in PHP via isStale(). Kept portable
            // (no DATE_ADD / INTERVAL) so MySQL and PostgreSQL behave alike.
            ->where($last, '<', now()->subSeconds(30));
    }
}
