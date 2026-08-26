<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\ServerMetricRepositoryInterface;
use App\Enums\MetricType;
use App\Models\Server;
use App\Models\ServerMetric;
use App\Models\ServerMetricRollup;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Metric reads and bulk writes.
 *
 * The central idea: a chart request never returns raw rows. It is answered from
 * the cheapest source that still has the requested resolution — recent windows
 * from `server_metrics`, longer ones from pre-aggregated
 * `server_metric_rollups` — and always bucketed and capped in SQL. That is what
 * makes a two-year chart cost roughly the same as a one-hour chart, and what
 * stops one client from asking for the entire history of the estate.
 */
class ServerMetricRepository implements ServerMetricRepositoryInterface
{
    /**
     * A bounded, bucketed series for one server and one metric.
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
    ): array {
        $spanHours = max(1, (int) ceil($from->diffInMinutes($to) / 60));
        $plan = $this->resolutionFor($spanHours);
        $maxPoints = (int) Config::get('monitoring.limits.max_metric_points', 1000);

        $points = $plan['source'] === 'rollup'
            ? $this->rollupSeries($server, $type, $from, $to, $plan['bucket'], $maxPoints + 1)
            : $this->rawSeries($server, $type, $from, $to, $plan['bucket'], $maxPoints + 1);

        // Fetching one extra row is how we know the window was larger than the
        // cap without running a second COUNT query.
        $truncated = count($points) > $maxPoints;

        if ($truncated) {
            $points = array_slice($points, 0, $maxPoints);
        }

        return [
            'source' => $plan['source'],
            'bucket' => $plan['bucket'],
            'points' => $points,
            'truncated' => $truncated,
        ];
    }

    /**
     * Pick source + bucket from config by span.
     *
     * Driven entirely by `config('monitoring.resolution')`, so the resolution
     * policy is tunable without touching this class.
     *
     * @return array{source: string, bucket: string|null}
     */
    private function resolutionFor(int $spanHours): array
    {
        /** @var array<int, array{max_hours: int|null, source: string, bucket: string|null}> $tiers */
        $tiers = (array) Config::get('monitoring.resolution', []);

        foreach ($tiers as $tier) {
            $max = $tier['max_hours'] ?? null;

            if ($max === null || $spanHours <= (int) $max) {
                return [
                    'source' => (string) ($tier['source'] ?? 'raw'),
                    'bucket' => $tier['bucket'] ?? null,
                ];
            }
        }

        return ['source' => 'rollup', 'bucket' => ServerMetricRollup::PERIOD_DAY];
    }

    /**
     * Raw samples, optionally bucketed by hour.
     *
     * Bucketing is done with a GROUP BY on a truncated timestamp so the
     * aggregation happens in the database — the application never receives more
     * rows than it returns.
     *
     * @return array<int, array{t: string, avg: float, min: float, max: float}>
     */
    private function rawSeries(
        Server $server,
        MetricType $type,
        CarbonInterface $from,
        CarbonInterface $to,
        ?string $bucket,
        int $limit,
    ): array {
        $query = ServerMetric::query()
            ->where('server_id', $server->getKey())
            ->ofType($type)
            ->between($from, $to);

        if ($bucket === null) {
            // Un-bucketed: still capped, and ordered by the covering index.
            $rows = $query
                ->select(['recorded_at', 'value'])
                ->orderBy('recorded_at')
                ->limit($limit)
                ->get();

            return $rows->map(static fn (ServerMetric $row): array => [
                't' => $row->recorded_at?->toIso8601String() ?? '',
                'avg' => (float) $row->value,
                'min' => (float) $row->value,
                'max' => (float) $row->value,
            ])->all();
        }

        $expression = $this->bucketExpression('recorded_at', $bucket);

        $rows = $query
            ->selectRaw("{$expression} as bucket_t, avg(value) as avg_value, min(value) as min_value, max(value) as max_value")
            ->groupByRaw($expression)
            ->orderByRaw('bucket_t asc')
            ->limit($limit)
            ->get();

        return $this->mapAggregateRows($rows->all());
    }

    /**
     * Pre-aggregated buckets.
     *
     * When the requested bucket is coarser than the stored period (a daily view
     * over hourly rollups), the rows are re-aggregated in SQL using `samples` as
     * the weight, so the average stays correct rather than becoming an average
     * of averages.
     *
     * @return array<int, array{t: string, avg: float, min: float, max: float}>
     */
    private function rollupSeries(
        Server $server,
        MetricType $type,
        CarbonInterface $from,
        CarbonInterface $to,
        ?string $bucket,
        int $limit,
    ): array {
        $period = $bucket === ServerMetricRollup::PERIOD_DAY
            ? ServerMetricRollup::PERIOD_DAY
            : ServerMetricRollup::PERIOD_HOUR;

        $query = ServerMetricRollup::query()
            ->where('server_id', $server->getKey())
            ->where('metric_type', $type->value)
            ->period($period)
            ->whereBetween('bucket_start', [$from, $to]);

        $rows = $query
            ->selectRaw(
                'bucket_start as bucket_t,'
                // Weighted mean: sum(avg * samples) / sum(samples).
                .' case when sum(samples) > 0 then sum(avg_value * samples) / sum(samples)'
                .' else avg(avg_value) end as avg_value,'
                .' min(min_value) as min_value, max(max_value) as max_value'
            )
            ->groupBy('bucket_start')
            ->orderBy('bucket_start')
            ->limit($limit)
            ->get();

        // A daily view may legitimately have no daily rollups yet (the daily job
        // runs once a day), so fall back to folding the hourly buckets.
        if ($rows->isEmpty() && $period === ServerMetricRollup::PERIOD_DAY) {
            return $this->foldHourlyToDaily($server, $type, $from, $to, $limit);
        }

        return $this->mapAggregateRows($rows->all());
    }

    /**
     * Daily points derived from hourly rollups, weighted by sample count.
     *
     * @return array<int, array{t: string, avg: float, min: float, max: float}>
     */
    private function foldHourlyToDaily(
        Server $server,
        MetricType $type,
        CarbonInterface $from,
        CarbonInterface $to,
        int $limit,
    ): array {
        $expression = $this->bucketExpression('bucket_start', ServerMetricRollup::PERIOD_DAY);

        $rows = ServerMetricRollup::query()
            ->where('server_id', $server->getKey())
            ->where('metric_type', $type->value)
            ->period(ServerMetricRollup::PERIOD_HOUR)
            ->whereBetween('bucket_start', [$from, $to])
            ->selectRaw(
                "{$expression} as bucket_t,"
                .' case when sum(samples) > 0 then sum(avg_value * samples) / sum(samples)'
                .' else avg(avg_value) end as avg_value,'
                .' min(min_value) as min_value, max(max_value) as max_value'
            )
            ->groupByRaw($expression)
            ->orderByRaw('bucket_t asc')
            ->limit($limit)
            ->get();

        return $this->mapAggregateRows($rows->all());
    }

    /**
     * @param  array<int, object>  $rows
     * @return array<int, array{t: string, avg: float, min: float, max: float}>
     */
    private function mapAggregateRows(array $rows): array
    {
        return array_map(static function (object $row): array {
            /** @var object{bucket_t: mixed, avg_value: mixed, min_value: mixed, max_value: mixed} $row */
            $bucket = $row->bucket_t;

            return [
                't' => $bucket instanceof CarbonInterface
                    ? $bucket->toIso8601String()
                    : (string) $bucket,
                'avg' => round((float) $row->avg_value, 4),
                'min' => round((float) $row->min_value, 4),
                'max' => round((float) $row->max_value, 4),
            ];
        }, $rows);
    }

    /**
     * Truncate a timestamp column to a bucket boundary.
     *
     * Uses DATE_FORMAT (MySQL) which is index-friendly enough here because the
     * range predicate on the leading index columns has already narrowed the scan.
     */
    private function bucketExpression(string $column, string $bucket): string
    {
        return match ($bucket) {
            ServerMetricRollup::PERIOD_DAY => "date_format({$column}, '%Y-%m-%d 00:00:00')",
            default => "date_format({$column}, '%Y-%m-%d %H:00:00')",
        };
    }

    /**
     * Latest sample per metric type, in ONE query.
     *
     * Implemented with a correlated MAX(recorded_at) rather than N queries or a
     * window function, so the cost does not grow with the number of metric types
     * an agent reports.
     *
     * @param  array<int, MetricType>  $types
     * @return array<string, array{value: float, recorded_at: string, metric_key: string|null}>
     */
    public function latestFor(Server $server, array $types = []): array
    {
        $serverId = (int) $server->getKey();

        $inner = DB::table('server_metrics')
            ->selectRaw('metric_type, max(recorded_at) as latest_at')
            ->where('server_id', $serverId)
            ->groupBy('metric_type');

        if ($types !== []) {
            $inner->whereIn('metric_type', array_map(
                static fn (MetricType $type): string => $type->value,
                $types,
            ));
        }

        $rows = DB::table('server_metrics as m')
            ->joinSub($inner, 'latest', function ($join): void {
                $join->on('m.metric_type', '=', 'latest.metric_type')
                    ->on('m.recorded_at', '=', 'latest.latest_at');
            })
            ->where('m.server_id', $serverId)
            ->select(['m.metric_type', 'm.metric_key', 'm.value', 'm.recorded_at'])
            ->get();

        $latest = [];

        foreach ($rows as $row) {
            /** @var object{metric_type: string, metric_key: string|null, value: mixed, recorded_at: mixed} $row */
            $latest[$row->metric_type] = [
                'value' => (float) $row->value,
                'recorded_at' => (string) $row->recorded_at,
                'metric_key' => $row->metric_key,
            ];
        }

        return $latest;
    }

    /**
     * ONE bulk insert. No model events, no per-row queries.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function insertMany(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        ServerMetric::query()->insert($rows);

        return count($rows);
    }
}
