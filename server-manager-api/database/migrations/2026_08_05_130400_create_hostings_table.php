<?php

declare(strict_types=1);

use App\Enums\HostingStatus;
use App\Enums\ServerType;
use App\Enums\SslCertificate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hostings', function (Blueprint $table): void {
            $table->id();

            $table->string('title');

            // Prefer the catalogued provider; `provider_name`/`provider_url`
            // carry an ad-hoc vendor when it is not in the catalogue yet.
            $table->foreignId('provider_id')->nullable()->constrained('providers')->nullOnDelete();
            $table->string('provider_name')->nullable();
            $table->string('provider_url')->nullable();

            $table->string('server_type', 32)->default(ServerType::Shared->value);
            $table->string('server_location')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('control_panel_url')->nullable();

            $table->string('username')->nullable();
            // Encrypted at rest via Crypt::encryptString() — never indexed,
            // never searchable, length unbounded because ciphertext expands.
            $table->text('password')->nullable();

            $table->string('ftp_username')->nullable();
            $table->text('ftp_password')->nullable();

            $table->date('purchase_date')->nullable();
            $table->date('expiry_date')->nullable();

            $table->string('plan')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->char('currency', 3)->default('USD');

            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();

            $table->string('status', 20)->default(HostingStatus::Active->value);
            $table->string('ssl_certificate', 32)->default(SslCertificate::None->value);

            $table->boolean('expiry_notification')->default(true);
            $table->unsignedSmallInteger('notification_days_before')->default(30);
            $table->timestamp('expiry_notified_at')->nullable();

            $table->text('description')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index('title');
            $table->index('status');
            $table->index('server_type');
            $table->index('expiry_date');
            // Drives the dashboard's "expiring hostings" window scan.
            $table->index(['status', 'expiry_date']);
            $table->index(['expiry_notification', 'expiry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hostings');
    }
};
