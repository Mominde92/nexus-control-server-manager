<?php

declare(strict_types=1);

use App\Enums\ProviderStatus;
use App\Enums\ProviderType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('providers', function (Blueprint $table): void {
            $table->id();

            $table->string('name');
            $table->string('url')->nullable();
            $table->string('type', 32)->default(ProviderType::Other->value);
            $table->string('status', 20)->default(ProviderStatus::Active->value);

            $table->string('support_email')->nullable();
            $table->string('support_phone', 32)->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
            $table->index('type');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('providers');
    }
};
