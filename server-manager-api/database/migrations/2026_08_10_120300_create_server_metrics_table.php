<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Raw metric samples — the highest-volume table in the system.
 *
 * KEY DESIGN DECISION: metrics are stored **long** (one row per sample per
 * metric), not **wide** (a column per metric). A wide table would need an
 * ALTER TABLE for every new metric an agent learns to report; the long shape
 * makes "add a metric" a pure data concern. `metric_key` carries the original
 * agent-supplied name so unrecognised metrics are retained verbatim rather than
 * dropped.
 *
 * Retention: this table holds only the recent window (default 7 days, see
 * config('monitoring.retention')). Anything older is rolled up into
 * `server_metric_rollups` and pruned, which is what keeps query cost flat as
 * servers are added.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_metrics', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('server_id')->constrained('servers')->cascadeOnDelete();

            $table->string('metric_type', 32);
            // Agent-supplied key; equals metric_type for known types, and holds
            // the real name (e.g. "queue_depth") when metric_type is 'custom'.
            $table->string('metric_key', 64)->nullable();

            // 20,4 covers percentages, load averages, byte counters and uptime
            // without the rounding surprises of a float.
            $table->decimal('value', 20, 4);

            $table->timestamp('recorded_at');

            $table->timestamp('created_at')->nullable();

            // THE index that matters: every chart query is
            // "one server, one metric, a time range, ordered by time".
            $table->index(['server_id', 'metric_type', 'recorded_at'], 'sm_server_metric_time_idx');
            // Supports the pruning sweep and cross-server time-window scans.
            $table->index('recorded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_metrics');
    }
};
