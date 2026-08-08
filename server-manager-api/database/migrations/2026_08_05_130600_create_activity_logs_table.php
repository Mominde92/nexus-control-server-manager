<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only audit trail. Rows are never updated or soft-deleted: retention is
 * handled by the `activity:prune` command (see docs/DEPLOYMENT.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table): void {
            $table->id();

            // Null when the actor is unauthenticated (e.g. a failed login).
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // Denormalised so the trail survives user deletion.
            $table->string('user_name')->nullable();

            $table->string('action', 48);

            // The affected record, as a nullable polymorphic reference.
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            // Human label captured at write time ("Production Server").
            $table->string('subject_label')->nullable();

            $table->text('description')->nullable();

            // Field-level before/after diff, and any extra context.
            $table->json('properties')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamps();

            $table->index('action');
            $table->index('created_at');
            $table->index(['subject_type', 'subject_id']);
            // Drives "recent activity by user" on the dashboard.
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
