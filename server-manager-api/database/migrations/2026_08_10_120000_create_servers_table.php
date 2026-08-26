<?php

declare(strict_types=1);

use App\Enums\ServerOs;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Monitored servers.
 *
 * One row per server, created dynamically through the API — there is no fixed
 * server count anywhere in the schema or the application. A server optionally
 * links to a Hosting record, so an existing hosting account can be promoted to
 * a monitored host without duplicating its details.
 *
 * Per-server thresholds and intervals live here (not in config), so operators
 * tune noisy hosts individually without a deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servers', function (Blueprint $table): void {
            $table->id();

            $table->string('name');
            $table->string('hostname')->nullable();
            $table->string('ip_address', 45)->nullable();

            // OS family is a label + agent-selection hint, never a logic branch.
            $table->string('os', 32)->default(ServerOs::Other->value);
            $table->string('os_version')->nullable();

            // Optional link to the existing inventory. nullOnDelete: removing a
            // hosting record must not silently delete monitoring history.
            $table->foreignId('hosting_id')->nullable()->constrained('hostings')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();

            $table->boolean('monitoring_enabled')->default(false);

            // Expected seconds between heartbeats, and how long past that we
            // wait before declaring the host offline.
            $table->unsignedSmallInteger('heartbeat_interval_seconds')->default(60);
            $table->unsignedSmallInteger('heartbeat_grace_seconds')->default(150);

            // Per-server threshold overrides; null falls back to
            // MetricType::defaultThresholds().
            $table->unsignedTinyInteger('cpu_warning_threshold')->nullable();
            $table->unsignedTinyInteger('cpu_critical_threshold')->nullable();
            $table->unsignedTinyInteger('memory_warning_threshold')->nullable();
            $table->unsignedTinyInteger('memory_critical_threshold')->nullable();
            $table->unsignedTinyInteger('disk_warning_threshold')->nullable();
            $table->unsignedTinyInteger('disk_critical_threshold')->nullable();

            // Denormalised heartbeat cursor. Written on every heartbeat so the
            // dashboard can compute health for N servers without touching the
            // (much larger) heartbeats or metrics tables.
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->string('agent_version')->nullable();
            $table->unsignedBigInteger('uptime_seconds')->nullable();

            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
            $table->index('os');
            $table->index('hosting_id');
            // Drives the dashboard's "which servers are live / stale" scan:
            // filter on monitoring_enabled, then order/compare last_heartbeat_at.
            $table->index(['monitoring_enabled', 'last_heartbeat_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servers');
    }
};
