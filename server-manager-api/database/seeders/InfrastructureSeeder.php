<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\HostingStatus;
use App\Enums\ProviderStatus;
use App\Enums\ProviderType;
use App\Enums\ServerType;
use App\Models\Hosting;
use App\Models\Provider;
use App\Models\Domain;
use App\Enums\DomainStatus;
use App\Enums\DomainType;
use Illuminate\Database\Seeder;

final class InfrastructureSeeder extends Seeder
{
    public function run(): void
    {
        // All records in this seeder are deliberately fictional. The example.com
        // domain and TEST-NET addresses are reserved for documentation and demos.
        $demoProvider = Provider::withTrashed()->updateOrCreate(
            ['name' => 'Example Cloud'],
            [
                'url' => 'https://portal.example.com',
                'type' => ProviderType::Hosting->value,
                'status' => ProviderStatus::Active->value,
                'notes' => 'Fictional provider used for prototype demonstrations.',
            ],
        );

        $records = [
            [
                'title' => 'Demo Hosting Account',
                'provider_id' => $demoProvider->id,
                'provider_url' => $demoProvider->url,
                'server_type' => ServerType::Shared->value,
                'username' => 'demo@example.com',
                'purchase_date' => '2026-01-15',
                'description' => 'Fictional shared hosting record for product demonstrations.',
            ],
            [
                'title' => 'Demo Windows Server',
                'server_type' => ServerType::Dedicated->value,
                'ip_address' => '192.0.2.10',
                'username' => 'demo-admin',
                'description' => 'Fictional Windows server using a TEST-NET address.',
            ],
            [
                'title' => 'Demo Ubuntu Server',
                'server_type' => ServerType::Dedicated->value,
                'ip_address' => '198.51.100.20',
                'username' => 'demo-operator',
                'description' => 'Fictional Linux server using a TEST-NET address.',
            ],
            [
                'title' => 'Demo File Server',
                'server_type' => ServerType::Dedicated->value,
                'ip_address' => '203.0.113.30',
                'username' => 'demo-user',
                'description' => 'Fictional file server using a TEST-NET address.',
            ],
            [
                'title' => 'Demo VMware RDP Host',
                'server_type' => ServerType::Dedicated->value,
                'ip_address' => '192.0.2.40',
                'username' => 'demo-rdp',
                'description' => 'Fictional VMware RDP host using a TEST-NET address.',
            ],
        ];

        foreach ($records as $record) {
            Hosting::withTrashed()->updateOrCreate(
                ['title' => $record['title'], 'ip_address' => $record['ip_address'] ?? null],
                [...$record, 'status' => HostingStatus::Active->value],
            );
        }

        Domain::withTrashed()->updateOrCreate(
            ['name' => 'example.com'],
            [
                'domain_type' => DomainType::Primary->value,
                'status' => DomainStatus::Active->value,
                'expiry_date' => '2027-12-31',
                'registrar_url' => 'https://registrar.example.com',
                'expiry_notification' => true,
                'notification_days_before' => 30,
                'description' => 'Fictional domain record for product demonstrations.',
            ],
        );
    }
}
