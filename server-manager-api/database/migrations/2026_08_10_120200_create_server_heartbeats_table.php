<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Heartbeat log — one row per agent check-in.
 *
 * This is an append-only, high-volume table (N servers × 1440/day at a 60s
 * interval). Two consequences drive the design:
 *
 *   1. No soft deletes and no updates — rows are inserted and later pruned by
 *      `monitoring:prune`, which is far cheaper than maintaining them.
 *   2. Liveness checks never read this table. The `servers.last_heartbeat_at`
 *      cursor answers "is it up?" in one indexed read, so the dashboard cost is
 *      independent of how much history has accumulated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_heartbeats', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('server_id')->constrained('servers')->cascadeOnDelete();

            $table->timestamp('received_at')->index();

            $table->string('agent_version')->nullable();
            $table->unsignedBigInteger('uptime_seconds')->nullable();
            $table->string('source_ip', 45)->nullable();

            // Free-form agent payload (kernel build, container runtime, …).
            // JSON keeps new agent fields from requiring a migration.
            $table->json('payload')->nullable();

            // No updated_at: a heartbeat is never modified.
            $table->timestamp('created_at')->nullable();

            // Primary read pattern: latest-first history for ONE server.
            $table->index(['server_id', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_heartbeats');
    }
};
