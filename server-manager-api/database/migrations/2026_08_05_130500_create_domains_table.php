<?php

declare(strict_types=1);

use App\Enums\DomainStatus;
use App\Enums\DomainType;
use App\Enums\RegistrarStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domains', function (Blueprint $table): void {
            $table->id();

            $table->string('name');

            $table->foreignId('provider_id')->nullable()->constrained('providers')->nullOnDelete();
            $table->string('provider_name')->nullable();
            $table->string('provider_url')->nullable();

            $table->string('domain_type', 32)->default(DomainType::Primary->value);

            // A domain may point at a hosting account; detaching the hosting
            // must not delete the domain record.
            $table->foreignId('hosting_id')->nullable()->constrained('hostings')->nullOnDelete();

            $table->string('username')->nullable();
            $table->text('password')->nullable();          // encrypted at rest

            $table->date('purchase_date')->nullable();
            $table->date('expiry_date')->nullable();

            $table->decimal('price', 12, 2)->nullable();
            $table->char('currency', 3)->default('USD');
            $table->string('plan')->nullable();

            $table->string('registrar_url')->nullable();
            $table->string('registrar_username')->nullable();
            $table->text('registrar_password')->nullable(); // encrypted at rest
            $table->string('registrar_status', 32)->default(RegistrarStatus::Active->value);

            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();

            $table->string('status', 20)->default(DomainStatus::Active->value);

            $table->boolean('auto_renewal')->default(false);
            $table->boolean('whois_protection')->default(false);

            $table->boolean('expiry_notification')->default(true);
            $table->unsignedSmallInteger('notification_days_before')->default(30);
            $table->timestamp('expiry_notified_at')->nullable();

            $table->text('description')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // A live domain name is unique, but soft-deleted rows may repeat it.
            $table->unique(['name', 'deleted_at']);
            $table->index('status');
            $table->index('registrar_status');
            $table->index('domain_type');
            $table->index('expiry_date');
            $table->index(['status', 'expiry_date']);
            $table->index(['expiry_notification', 'expiry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domains');
    }
};
