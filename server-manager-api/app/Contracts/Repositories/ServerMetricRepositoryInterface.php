<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Enums\MetricType;
use App\Models\Server;
use Carbon\CarbonInterface;

/**
 * Metric reads.
 *
 * The one part of the system where a naive implementation would not scale: a
 * 90-day chart at a 60s sample rate is ~130k raw rows *per server per metric*.
 * Implementations must downsample in SQL and bound the result.
 */
interface ServerMetricRepositoryInterface
{
    /**
     * A bounded, bucketed time series for one server and one metric.
     *
     * The source (raw vs rollup) and bucket width are chosen from
     * `config('monitoring.resolution')` by the requested span, and the point
     * count is capped at `config('monitoring.limits.max_metric_points')`.
     *
     * @return array{
     *     source: string,
     *     bucket: string|null,
     *     points: array<int, array{t: string, avg: float, min: float, max: float}>,
     *     truncated: bool
     * }
     */
    public function seriesFor(
        Server $server,
        MetricType $type,
        CarbonInterface $from,
        CarbonInterface $to,
    ): array;

    /**
     * The most recent sample per metric type for one server, resolved in ONE
     * query rather than one query per type.
     *
     * @param  array<int, MetricType>  $types  Empty = every type present.
     * @return array<string, array{value: float, recorded_at: string, metric_key: string|null}>
     */
    public function latestFor(Server $server, array $types = []): array;

    /**
     * Bulk-insert raw samples. One INSERT, no model events.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function insertMany(array $rows): int;
}
