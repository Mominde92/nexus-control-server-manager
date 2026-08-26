<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pre-aggregated metric buckets (hourly and daily).
 *
 * Why this table exists: a 90-day CPU chart for one server is ~130k raw rows at
 * a 60s sample rate. Reading and downsampling that per request does not scale
 * with server count. `monitoring:rollup` folds raw samples into fixed buckets
 * once, so a 90-day chart reads ~90 daily rows instead.
 *
 * min/max are kept alongside avg because averaging away a 100% CPU spike is
 * exactly the information an operator needs; `samples` allows correct weighted
 * re-aggregation (hourly -> daily) without returning to the raw table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_metric_rollups', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('server_id')->constrained('servers')->cascadeOnDelete();

            $table->string('metric_type', 32);
            $table->string('metric_key', 64)->nullable();

            // 'hour' | 'day' — coarser buckets can be added without a migration.
            $table->string('period', 8);
            // Truncated bucket start (e.g. 2026-08-10 14:00:00 for an hour).
            $table->timestamp('bucket_start');

            $table->decimal('avg_value', 20, 4);
            $table->decimal('min_value', 20, 4);
            $table->decimal('max_value', 20, 4);
            $table->unsignedInteger('samples')->default(0);

            $table->timestamps();

            // Idempotent rollups: re-running the job updates the bucket in place
            // instead of duplicating it.
            $table->unique(
                ['server_id', 'metric_type', 'metric_key', 'period', 'bucket_start'],
                'smr_unique_bucket'
            );
            // The chart read path.
            $table->index(['server_id', 'metric_type', 'period', 'bucket_start'], 'smr_read_idx');
            $table->index(['period', 'bucket_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_metric_rollups');
    }
};
