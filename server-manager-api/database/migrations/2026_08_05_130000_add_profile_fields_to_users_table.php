<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extends Laravel's stock `users` table with the profile, lifecycle and audit
 * columns the Server Manager needs. Kept as a separate migration so the
 * framework's own migration stays untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('phone', 32)->nullable()->after('email');
            $table->string('job_title')->nullable()->after('phone');
            $table->string('avatar_path')->nullable()->after('job_title');

            $table->string('status', 20)
                ->default(UserStatus::Active->value)
                ->after('avatar_path');

            $table->timestamp('last_login_at')->nullable()->after('status');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');

            // Nullable: the very first Admin is created by the seeder.
            $table->foreignId('created_by')
                ->nullable()
                ->after('last_login_ip')
                ->constrained('users')
                ->nullOnDelete();

            $table->softDeletes();

            $table->index('status');
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['created_by']);
            $table->dropIndex(['status']);
            $table->dropIndex(['name']);

            $table->dropColumn([
                'phone',
                'job_title',
                'avatar_path',
                'status',
                'last_login_at',
                'last_login_ip',
                'created_by',
                'deleted_at',
            ]);
        });
    }
};
