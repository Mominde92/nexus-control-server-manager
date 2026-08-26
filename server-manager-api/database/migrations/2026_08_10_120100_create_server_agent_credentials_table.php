<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-server agent credentials.
 *
 * Unlike the vendor passwords elsewhere in this app (which are *encrypted*
 * because an operator must read them back), an agent token is a bearer secret
 * that nobody needs to recover — so it is stored as a one-way SHA-256 hash and
 * shown exactly once at issue time. A database leak therefore yields no usable
 * agent tokens.
 *
 * Rotation keeps history: a rotated credential is revoked, not deleted, and the
 * successor points back at it via `rotated_from_id`. That preserves the audit
 * trail of who rotated what, and lets an overlap window exist while an agent
 * picks up its new token.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_agent_credentials', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('server_id')->constrained('servers')->cascadeOnDelete();

            $table->string('name')->nullable();

            // Public, non-secret lookup key. Lets ingest find the row with one
            // indexed hit instead of hashing against every credential.
            $table->string('token_id', 32)->unique();
            // SHA-256 of the secret half. Never reversible.
            $table->string('token_hash', 64);
            // First few chars of the secret, for "…ends in ab12" style display.
            $table->string('token_hint', 12)->nullable();

            $table->timestamp('last_used_at')->nullable();
            $table->string('last_used_ip', 45)->nullable();

            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_reason')->nullable();

            $table->foreignId('rotated_from_id')
                ->nullable()
                ->constrained('server_agent_credentials')
                ->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Ingest path: resolve an active credential for a server.
            $table->index(['server_id', 'revoked_at']);
            $table->index('token_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_agent_credentials');
    }
};
