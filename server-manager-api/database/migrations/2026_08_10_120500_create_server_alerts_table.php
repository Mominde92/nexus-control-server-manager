<?php

declare(strict_types=1);

use App\Enums\AlertSeverity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Server alerts.
 *
 * Modelled as *stateful incidents*, not as one row per breaching sample: a host
 * pinned at 95% CPU for an hour produces ONE open alert that later resolves,
 * rather than 60 rows. `fingerprint` is what makes that deduplication possible —
 * ingest looks for an existing open alert with the same fingerprint before
 * creating a new one, which is also what stops a flapping host from flooding the
 * table as server count grows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_alerts', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('server_id')->constrained('servers')->cascadeOnDelete();

            $table->string('severity', 16)->default(AlertSeverity::Warning->value);
            // e.g. 'metric_threshold' | 'offline' | 'agent_error'
            $table->string('type', 32);
            // Stable identity of the condition, e.g. "cpu_percent:critical".
            $table->string('fingerprint', 128);

            $table->string('title');
            $table->text('message')->nullable();

            $table->string('metric_type', 32)->nullable();
            $table->decimal('observed_value', 20, 4)->nullable();
            $table->decimal('threshold_value', 20, 4)->nullable();

            $table->timestamp('started_at');
            $table->timestamp('last_observed_at')->nullable();
            // Null == still open. This is the field every alert query filters on.
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedInteger('occurrences')->default(1);

            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('notified_at')->nullable();

            $table->timestamps();

            // Dedupe lookup: at most one OPEN alert per condition per server.
            // Partial indexes are not portable, so uniqueness is enforced in the
            // service layer; this index makes that check a single indexed hit.
            $table->index(['server_id', 'fingerprint', 'resolved_at'], 'sa_dedupe_idx');
            // "Show me everything currently wrong", worst-first.
            $table->index(['resolved_at', 'severity']);
            $table->index(['server_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_alerts');
    }
};
