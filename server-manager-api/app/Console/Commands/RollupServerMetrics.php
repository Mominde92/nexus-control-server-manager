<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ServerMetricRollup;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Folds raw metric samples into pre-aggregated buckets.
 *
 * The whole point of this command is that it does NOT iterate servers. One
 * grouped SQL aggregate covers the entire estate per run — `GROUP BY server_id,
 * metric_type, metric_key, bucket` — so the work is proportional to the number of
 * samples in the window, never to a per-server loop. Adding a thousand servers
 * changes the row count, not the number of queries.
 *
 * `upsert()` on the unique bucket index makes re-runs idempotent, which matters
 * because the schedule may overlap a manual run or a retry.
 */
class RollupServerMetrics extends Command
{
    /**
     * @var string
     */
    protected $signature = 'monitoring:rollup
                            {--period=hour : Bucket period to build (hour|day)}
                            {--hours= : How far back to rebuild (defaults to a small trailing window)}';

    /**
     * @var string
     */
    protected $description = 'Fold raw server metrics into hourly or daily rollups';

    /**
     * Rows upserted per statement. Bounded so a large backfill never builds one
     * enormous query.
     */
    private const int UPSERT_CHUNK = 1000;

    public function handle(): int
    {
        $period = (string) $this->option('period');

        if (! in_array($period, [ServerMetricRollup::PERIOD_HOUR, ServerMetricRollup::PERIOD_DAY], true)) {
            $this->error('The --period option must be "hour" or "day".');

            return self::FAILURE;
        }

        // Default windows overlap the previous run so a bucket that was still
        // filling when the last run happened is recomputed rather than left
        // permanently partial.
        $hours = $this->option('hours') !== null
            ? max(1, (int) $this->option('hours'))
            : ($period === ServerMetricRollup::PERIOD_DAY ? 48 : 3);

        $since = now()->subHours($hours);

        $rows = $period === ServerMetricRollup::PERIOD_DAY
            ? $this->aggregateFromHourlyRollups($since)
            : $this->aggregateFromRawSamples($since);

        if ($rows === []) {
            $this->info("No samples to roll up for period '{$period}' since {$since->toDateTimeString()}.");

            return self::SUCCESS;
        }

        $written = $this->upsert($rows, $period);

        $this->info(sprintf(
            "Rolled up %d '%s' bucket(s) from %s onwards.",
            $written,
            $period,
            $since->toDateTimeString(),
        ));

        return self::SUCCESS;
    }

    /**
     * Hourly buckets straight from the raw samples — ONE grouped aggregate.
     *
     * @return array<int, array<string, mixed>>
     */
    private function aggregateFromRawSamples(CarbonInterface $since): array
    {
        return DB::table('server_metrics')
            ->selectRaw(
                'server_id, metric_type, metric_key,'
                ." date_format(recorded_at, '%Y-%m-%d %H:00:00') as bucket_start,"
                .' avg(value) as avg_value, min(value) as min_value,'
                .' max(value) as max_value, count(*) as samples'
            )
            ->where('recorded_at', '>=', $since)
            ->groupByRaw("server_id, metric_type, metric_key, date_format(recorded_at, '%Y-%m-%d %H:00:00')")
            ->get()
            ->map(static fn (object $row): array => (array) $row)
            ->all();
    }

    /**
     * Daily buckets folded from the hourly rollups rather than the raw table.
     *
     * Reading the (much smaller) hourly table keeps the daily job cheap and lets
     * it keep working after raw samples have been pruned. `samples` is used as the
     * weight so the daily mean is a true average, not an average of averages.
     *
     * @return array<int, array<string, mixed>>
     */
    private function aggregateFromHourlyRollups(CarbonInterface $since): array
    {
        return DB::table('server_metric_rollups')
            ->selectRaw(
                'server_id, metric_type, metric_key,'
                ." date_format(bucket_start, '%Y-%m-%d 00:00:00') as bucket_start,"
                .' case when sum(samples) > 0 then sum(avg_value * samples) / sum(samples)'
                .' else avg(avg_value) end as avg_value,'
                .' min(min_value) as min_value, max(max_value) as max_value,'
                .' sum(samples) as samples'
            )
            ->where('period', ServerMetricRollup::PERIOD_HOUR)
            ->where('bucket_start', '>=', $since)
            ->groupByRaw("server_id, metric_type, metric_key, date_format(bucket_start, '%Y-%m-%d 00:00:00')")
            ->get()
            ->map(static fn (object $row): array => (array) $row)
            ->all();
    }

    /**
     * Idempotent write against the unique bucket index.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function upsert(array $rows, string $period): int
    {
        $now = now();
        $written = 0;

        foreach (array_chunk($rows, self::UPSERT_CHUNK) as $chunk) {
            $payload = array_map(static fn (array $row): array => [
                'server_id' => (int) $row['server_id'],
                'metric_type' => (string) $row['metric_type'],
                // Part of the unique key, so it must never be null.
                'metric_key' => (string) ($row['metric_key'] ?? $row['metric_type']),
                'period' => $period,
                'bucket_start' => (string) $row['bucket_start'],
                'avg_value' => round((float) $row['avg_value'], 4),
                'min_value' => round((float) $row['min_value'], 4),
                'max_value' => round((float) $row['max_value'], 4),
                'samples' => (int) $row['samples'],
                'created_at' => $now,
                'updated_at' => $now,
            ], $chunk);

            DB::table('server_metric_rollups')->upsert(
                $payload,
                ['server_id', 'metric_type', 'metric_key', 'period', 'bucket_start'],
                ['avg_value', 'min_value', 'max_value', 'samples', 'updated_at'],
            );

            $written += count($payload);
        }

        return $written;
    }
}
