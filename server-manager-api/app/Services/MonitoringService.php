<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\ServerAlertRepositoryInterface;
use App\Contracts\Repositories\ServerMetricRepositoryInterface;
use App\Contracts\Repositories\ServerRepositoryInterface;
use App\Contracts\Services\MonitoringServiceInterface;
use App\Enums\AlertSeverity;
use App\Enums\MetricType;
use App\Models\Server;
use App\Models\ServerAlert;
use App\Models\ServerHeartbeat;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The monitoring pipeline.
 *
 * Ingest is the hot path — it runs once per server per interval, so its cost
 * must not grow with the size of the estate. Two rules follow from that and are
 * enforced throughout this class:
 *
 *   - a batch of samples is ONE insert, never a loop of saves; and
 *   - threshold evaluation is dispatched to a queue by the caller rather than
 *     run inline, so ingest latency stays flat.
 */
class MonitoringService implements MonitoringServiceInterface
{
    /**
     * Levels evaluated for every percentage metric, worst first — so a value
     * over the critical line never registers merely as a warning.
     *
     * @var array<int, array{level: string, severity: AlertSeverity}>
     */
    private const array LEVELS = [
        ['level' => 'critical', 'severity' => AlertSeverity::Critical],
        ['level' => 'warning', 'severity' => AlertSeverity::Warning],
    ];

    public function __construct(
        private readonly ServerRepositoryInterface $servers,
        private readonly ServerMetricRepositoryInterface $metrics,
        private readonly ServerAlertRepositoryInterface $alerts,
    ) {}

    // -----------------------------------------------------------------------
    // Ingest
    // -----------------------------------------------------------------------

    /**
     * Record a check-in and advance the denormalised heartbeat cursor.
     *
     * Two writes total: the append-only heartbeat row and one targeted update of
     * the cursor columns on `servers`. The cursor update uses `saveQuietly()`
     * because this telemetry changes on every interval and would otherwise
     * swamp the audit trail with meaningless diffs.
     *
     * @param  array<string, mixed>  $data
     */
    public function recordHeartbeat(Server $server, array $data): ServerHeartbeat
    {
        $receivedAt = now();

        $heartbeat = new ServerHeartbeat([
            'server_id' => $server->getKey(),
            'received_at' => $receivedAt,
            'agent_version' => $data['agent_version'] ?? null,
            'uptime_seconds' => isset($data['uptime_seconds']) ? (int) $data['uptime_seconds'] : null,
            'source_ip' => $data['source_ip'] ?? null,
            'payload' => $data['payload'] ?? null,
        ]);

        $heartbeat->save();

        $server->forceFill([
            'last_heartbeat_at' => $receivedAt,
            'agent_version' => $data['agent_version'] ?? $server->agent_version,
            'uptime_seconds' => isset($data['uptime_seconds'])
                ? (int) $data['uptime_seconds']
                : $server->uptime_seconds,
        ])->saveQuietly();

        return $heartbeat;
    }

    /**
     * Persist a batch of samples with ONE bulk insert.
     *
     * An unrecognised metric name is never rejected and never triggers a schema
     * change: it is stored under `MetricType::Custom` with the agent's original
     * name preserved in `metric_key`. That is what lets a new agent start
     * reporting something the server has never heard of.
     *
     * @param  array<int, array<string, mixed>>  $metrics
     * @return array{inserted: int, skipped: int}
     */
    public function recordMetrics(Server $server, array $metrics): array
    {
        $max = (int) Config::get('monitoring.limits.max_batch_metrics', 200);
        $now = now();
        $serverId = (int) $server->getKey();

        $rows = [];
        $skipped = 0;

        foreach ($metrics as $metric) {
            if (count($rows) >= $max) {
                $skipped++;

                continue;
            }

            if (! is_array($metric) || ! isset($metric['type'], $metric['value'])) {
                $skipped++;

                continue;
            }

            $rawType = (string) $metric['type'];
            $known = MetricType::tryFromValue($rawType);

            $rows[] = [
                'server_id' => $serverId,
                'metric_type' => ($known ?? MetricType::Custom)->value,
                // For a known type the key mirrors the type; for an unknown one it
                // carries the agent's original name verbatim.
                'metric_key' => Str::limit(
                    isset($metric['key']) && is_string($metric['key']) && $metric['key'] !== ''
                        ? $metric['key']
                        : $rawType,
                    64,
                    '',
                ),
                'value' => (float) $metric['value'],
                'recorded_at' => $this->parseRecordedAt($metric['recorded_at'] ?? null, $now),
                'created_at' => $now,
            ];
        }

        return [
            'inserted' => $this->metrics->insertMany($rows),
            'skipped' => $skipped,
        ];
    }

    /**
     * Trust the agent's clock only within a sane window: a wildly wrong client
     * clock must not be able to write samples into the far future (which would
     * poison every "latest value" read) or the distant past.
     */
    private function parseRecordedAt(mixed $value, CarbonInterface $now): string
    {
        if (! is_string($value) || $value === '') {
            return $now->toDateTimeString();
        }

        try {
            $parsed = Carbon::parse($value);
        } catch (\Throwable) {
            return $now->toDateTimeString();
        }

        if ($parsed->greaterThan($now->copy()->addMinutes(5))) {
            return $now->toDateTimeString();
        }

        if ($parsed->lessThan($now->copy()->subDays(7))) {
            return $now->toDateTimeString();
        }

        return $parsed->toDateTimeString();
    }

    // -----------------------------------------------------------------------
    // Threshold evaluation
    // -----------------------------------------------------------------------

    /**
     * Compare the latest sample of each thresholded metric against the server's
     * effective thresholds, then open, dedupe or resolve accordingly.
     *
     * Stateful by design: a host pinned above the line for an hour produces ONE
     * alert whose `occurrences` climbs, not sixty rows. When the value returns
     * below the line the alert resolves itself.
     *
     * @return array{opened: int, updated: int, resolved: int}
     */
    public function evaluateThresholds(Server $server): array
    {
        $thresholded = array_values(array_filter(
            MetricType::cases(),
            static fn (MetricType $type): bool => $type->isPercentage(),
        ));

        $latest = $this->metrics->latestFor($server, $thresholded);

        $result = ['opened' => 0, 'updated' => 0, 'resolved' => 0];

        foreach ($thresholded as $type) {
            $sample = $latest[$type->value] ?? null;

            if ($sample === null) {
                continue;
            }

            $breached = null;

            foreach (self::LEVELS as $definition) {
                $threshold = $server->thresholdFor($type, $definition['level']);

                if ($threshold === null) {
                    continue;
                }

                if ($sample['value'] >= $threshold) {
                    $breached = [
                        'level' => $definition['level'],
                        'severity' => $definition['severity'],
                        'threshold' => $threshold,
                    ];

                    break;
                }
            }

            $this->reconcileMetricAlerts($server, $type, $sample, $breached, $result);
        }

        return $result;
    }

    /**
     * Bring the stored alerts for one metric in line with the current reading.
     *
     * Both levels are reconciled, not just the breached one: recovering from
     * critical to merely warning must close the critical incident and open a
     * warning, otherwise the dashboard would keep showing the worse state.
     *
     * @param  array{value: float, recorded_at: string, metric_key: string|null}  $sample
     * @param  array{level: string, severity: AlertSeverity, threshold: float}|null  $breached
     * @param  array{opened: int, updated: int, resolved: int}  $result
     */
    private function reconcileMetricAlerts(
        Server $server,
        MetricType $type,
        array $sample,
        ?array $breached,
        array &$result,
    ): void {
        foreach (self::LEVELS as $definition) {
            $level = $definition['level'];
            $fingerprint = ServerAlert::fingerprintFor(
                ServerAlert::TYPE_METRIC_THRESHOLD,
                $type->value,
                $level,
            );

            $existing = $this->alerts->findOpenByFingerprint((int) $server->getKey(), $fingerprint);
            $isBreachedLevel = $breached !== null && $breached['level'] === $level;

            if ($isBreachedLevel) {
                if ($existing === null) {
                    $this->openMetricAlert($server, $type, $sample, $breached, $fingerprint);
                    $result['opened']++;

                    continue;
                }

                // Dedupe: bump the existing incident rather than inserting again.
                $existing->forceFill([
                    'occurrences' => $existing->occurrences + 1,
                    'last_observed_at' => now(),
                    'observed_value' => $sample['value'],
                ])->save();

                $result['updated']++;

                continue;
            }

            // Not breaching this level any more — close it if it was open.
            if ($existing !== null) {
                $existing->forceFill([
                    'resolved_at' => now(),
                    'last_observed_at' => now(),
                    'observed_value' => $sample['value'],
                ])->save();

                $result['resolved']++;
            }
        }
    }

    /**
     * @param  array{value: float, recorded_at: string, metric_key: string|null}  $sample
     * @param  array{level: string, severity: AlertSeverity, threshold: float}  $breached
     */
    private function openMetricAlert(
        Server $server,
        MetricType $type,
        array $sample,
        array $breached,
        string $fingerprint,
    ): void {
        $unit = $type->unit();

        ServerAlert::query()->create([
            'server_id' => $server->getKey(),
            'severity' => $breached['severity']->value,
            'type' => ServerAlert::TYPE_METRIC_THRESHOLD,
            'fingerprint' => $fingerprint,
            'title' => sprintf(
                '%s %s on %s',
                $type->label(),
                $breached['level'],
                $server->name,
            ),
            'message' => sprintf(
                '%s reached %s%s, at or above the %s threshold of %s%s.',
                $type->label(),
                round($sample['value'], 2),
                $unit,
                $breached['level'],
                round($breached['threshold'], 2),
                $unit,
            ),
            'metric_type' => $type->value,
            'observed_value' => $sample['value'],
            'threshold_value' => $breached['threshold'],
            'started_at' => now(),
            'last_observed_at' => now(),
            'occurrences' => 1,
        ]);
    }

    // -----------------------------------------------------------------------
    // Offline sweep
    // -----------------------------------------------------------------------

    /**
     * Open offline alerts for hosts that stopped reporting, and resolve them for
     * hosts that came back.
     *
     * The candidate set comes from `Server::stale()`, which reads only the
     * denormalised cursor on `servers` — never the heartbeats table — so the
     * sweep cost scales with server count, not with history size. The exact
     * per-server tolerance is then applied in PHP via `isStale()`, keeping the
     * SQL portable.
     *
     * @return array{checked: int, opened: int, resolved: int}
     */
    public function markOfflineServers(): array
    {
        $opened = 0;
        $resolved = 0;
        $checked = 0;

        $fingerprint = ServerAlert::fingerprintFor(ServerAlert::TYPE_OFFLINE, null);

        // Chunked so a large estate is swept in bounded memory.
        Server::query()
            ->stale()
            ->select([
                'id', 'name', 'monitoring_enabled', 'last_heartbeat_at',
                'heartbeat_interval_seconds', 'heartbeat_grace_seconds',
            ])
            ->chunkById(500, function ($servers) use (&$opened, &$checked, $fingerprint): void {
                foreach ($servers as $server) {
                    $checked++;

                    if (! $server->isStale()) {
                        continue;
                    }

                    $existing = $this->alerts->findOpenByFingerprint((int) $server->getKey(), $fingerprint);

                    if ($existing !== null) {
                        $existing->forceFill([
                            'occurrences' => $existing->occurrences + 1,
                            'last_observed_at' => now(),
                        ])->save();

                        continue;
                    }

                    ServerAlert::query()->create([
                        'server_id' => $server->getKey(),
                        'severity' => AlertSeverity::Critical->value,
                        'type' => ServerAlert::TYPE_OFFLINE,
                        'fingerprint' => $fingerprint,
                        'title' => sprintf('%s is offline', $server->name),
                        'message' => sprintf(
                            'No heartbeat received for more than %d seconds.',
                            $server->offlineAfterSeconds(),
                        ),
                        'started_at' => now(),
                        'last_observed_at' => now(),
                        'occurrences' => 1,
                    ]);

                    $opened++;
                }
            });

        $resolved = $this->resolveRecoveredServers($fingerprint);

        return ['checked' => $checked, 'opened' => $opened, 'resolved' => $resolved];
    }

    /**
     * Close offline alerts for hosts that have since reported.
     *
     * Candidates are found by joining open offline alerts to their server, so
     * only servers that actually have such an alert are examined — the recovery
     * check never scans the whole estate.
     */
    private function resolveRecoveredServers(string $fingerprint): int
    {
        $resolved = 0;

        ServerAlert::query()
            ->with('server:id,monitoring_enabled,last_heartbeat_at,heartbeat_interval_seconds,heartbeat_grace_seconds')
            ->where('fingerprint', $fingerprint)
            ->whereNull('resolved_at')
            ->chunkById(500, function ($alerts) use (&$resolved): void {
                foreach ($alerts as $alert) {
                    $server = $alert->server;

                    // Still genuinely down (or the server is gone): leave it open.
                    if ($server !== null && $server->monitoring_enabled && $server->isStale()) {
                        continue;
                    }

                    $alert->forceFill([
                        'resolved_at' => now(),
                        'last_observed_at' => now(),
                    ])->save();

                    $resolved++;
                }
            });

        return $resolved;
    }

    // -----------------------------------------------------------------------
    // Dashboard
    // -----------------------------------------------------------------------

    /**
     * Estate counters, cached briefly.
     *
     * The underlying queries are already single aggregates; the cache exists so
     * a dashboard open in several browser tabs does not re-run them on every
     * poll. The TTL is short enough that a state change surfaces promptly.
     *
     * @return array<string, mixed>
     */
    public function dashboardSummary(): array
    {
        $ttl = (int) Config::get('monitoring.limits.summary_cache_seconds', 15);

        $resolver = function (): array {
            $counts = $this->servers->summaryCounts();
            $alerts = $this->alerts->openCountsBySeverity();

            return [
                'servers' => $counts,
                'alerts' => [
                    'critical' => $alerts[AlertSeverity::Critical->value] ?? 0,
                    'warning' => $alerts[AlertSeverity::Warning->value] ?? 0,
                    'info' => $alerts[AlertSeverity::Info->value] ?? 0,
                    'total' => array_sum($alerts),
                ],
                'needs_attention' => $counts['critical'] + $counts['warning'] + $counts['offline'],
                'generated_at' => now()->toIso8601String(),
            ];
        };

        if ($ttl <= 0) {
            return $resolver();
        }

        return Cache::remember('monitoring:summary', $ttl, $resolver);
    }

    /**
     * Drop the cached summary so a write shows up immediately rather than after
     * the TTL.
     */
    public static function flushSummaryCache(): void
    {
        Cache::forget('monitoring:summary');
    }

    /**
     * Resolve every open alert for a server, used when monitoring is switched
     * off — a silenced host must not keep firing.
     */
    public function resolveAllAlertsFor(Server $server, ?string $reason = null): int
    {
        unset($reason);

        return DB::table('server_alerts')
            ->where('server_id', $server->getKey())
            ->whereNull('resolved_at')
            ->update([
                'resolved_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
