<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Models\Server;
use App\Models\ServerHeartbeat;

/**
 * The monitoring pipeline: ingest, threshold evaluation and estate rollup.
 */
interface MonitoringServiceInterface
{
    /**
     * Record one check-in and advance the server's heartbeat cursor.
     *
     * @param  array<string, mixed>  $data  Validated heartbeat payload.
     */
    public function recordHeartbeat(Server $server, array $data): ServerHeartbeat;

    /**
     * Persist a batch of samples with ONE bulk insert.
     *
     * An unrecognised metric name is stored as `MetricType::Custom` with the
     * original name kept in `metric_key` — never rejected, and never a schema
     * change.
     *
     * @param  array<int, array<string, mixed>>  $metrics
     * @return array{inserted: int, skipped: int}
     */
    public function recordMetrics(Server $server, array $metrics): array;

    /**
     * Compare the latest samples against the server's effective thresholds and
     * open, dedupe or resolve alerts accordingly.
     *
     * Runs out of band (queued) so ingest latency does not grow with the number
     * of thresholds or servers.
     *
     * @return array{opened: int, updated: int, resolved: int}
     */
    public function evaluateThresholds(Server $server): array;

    /**
     * Sweep for hosts that stopped reporting, opening/resolving offline alerts.
     *
     * @return array{checked: int, opened: int, resolved: int}
     */
    public function markOfflineServers(): array;

    /**
     * Estate-wide counters for the dashboard, cached for
     * `config('monitoring.limits.summary_cache_seconds')`.
     *
     * @return array<string, mixed>
     */
    public function dashboardSummary(): array;
}
