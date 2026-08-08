<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('record_access', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('accessible');
            $table->boolean('can_view')->default(true);
            $table->boolean('can_update')->default(false);
            $table->boolean('can_delete')->default(false);
            $table->boolean('can_view_credentials')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'accessible_type', 'accessible_id'], 'record_access_unique');
        });
    }

    public function down(): void { Schema::dropIfExists('record_access'); }
};
