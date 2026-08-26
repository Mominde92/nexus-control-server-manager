<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ServerMetricRollup;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Enforces the configured retention windows.
 *
 * This command is what keeps the monitoring tables from growing without bound as
 * servers are added: raw samples are kept only long enough for high-resolution
 * charts, then survive as rollups. Every window comes from
 * `config('monitoring.retention')` — none is hardcoded here.
 *
 * Deletes run in bounded chunks so a large prune never holds a long lock on a
 * table that ingest is concurrently writing to.
 */
class PruneMonitoringData extends Command
{
    /**
     * @var string
     */
    protected $signature = 'monitoring:prune {--dry-run : Report what would be deleted without deleting it}';

    /**
     * @var string
     */
    protected $description = 'Delete monitoring data past its configured retention window';

    public function handle(): int
    {
        $chunk = max(100, (int) Config::get('monitoring.retention.prune_chunk', 5000));
        $dryRun = (bool) $this->option('dry-run');

        $targets = [
            'server_metrics (raw)' => [
                'query' => fn (): Builder => DB::table('server_metrics')
                    ->where('recorded_at', '<', now()->subDays($this->days('raw_days', 7))),
            ],
            'server_metric_rollups (hourly)' => [
                'query' => fn (): Builder => DB::table('server_metric_rollups')
                    ->where('period', ServerMetricRollup::PERIOD_HOUR)
                    ->where('bucket_start', '<', now()->subDays($this->days('hourly_days', 90))),
            ],
            'server_metric_rollups (daily)' => [
                'query' => fn (): Builder => DB::table('server_metric_rollups')
                    ->where('period', ServerMetricRollup::PERIOD_DAY)
                    ->where('bucket_start', '<', now()->subDays($this->days('daily_days', 730))),
            ],
            'server_heartbeats' => [
                'query' => fn (): Builder => DB::table('server_heartbeats')
                    ->where('received_at', '<', now()->subDays($this->days('heartbeat_days', 14))),
            ],
            // Only resolved alerts are pruned. An open incident is never deleted,
            // however old it is — age is not the same as irrelevance.
            'server_alerts (resolved)' => [
                'query' => fn (): Builder => DB::table('server_alerts')
                    ->whereNotNull('resolved_at')
                    ->where('resolved_at', '<', now()->subDays($this->days('resolved_alert_days', 180))),
            ],
        ];

        $rows = [];
        $total = 0;

        foreach ($targets as $label => $target) {
            /** @var callable(): Builder $factory */
            $factory = $target['query'];

            $deleted = $dryRun
                ? $factory()->count()
                : $this->deleteInChunks($factory, $chunk);

            $rows[] = [$label, number_format($deleted)];
            $total += $deleted;
        }

        $this->table([$dryRun ? 'Table (dry run)' : 'Table', 'Rows'], $rows);

        $this->info(sprintf(
            '%s %s row(s) in chunks of %s.',
            $dryRun ? 'Would prune' : 'Pruned',
            number_format($total),
            number_format($chunk),
        ));

        return self::SUCCESS;
    }

    /**
     * Delete repeatedly in bounded batches until the window is clear.
     *
     * A single unbounded DELETE on a table with millions of rows would block
     * ingest; this keeps each statement short.
     *
     * @param  callable(): Builder  $factory
     */
    private function deleteInChunks(callable $factory, int $chunk): int
    {
        $deleted = 0;

        do {
            $affected = $factory()->limit($chunk)->delete();
            $deleted += $affected;
        } while ($affected === $chunk);

        return $deleted;
    }

    private function days(string $key, int $fallback): int
    {
        return max(1, (int) Config::get("monitoring.retention.{$key}", $fallback));
    }
}
