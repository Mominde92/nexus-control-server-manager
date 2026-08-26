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

        // Expiry dates are relative to "now" so the dashboard's expiring-soon
        // cards and the expiry:notify digest always have something to show,
        // however long after seeding the demo is run.
        $records = [
            [
                'title' => 'Demo Hosting Account',
                'provider_id' => $demoProvider->id,
                'provider_url' => $demoProvider->url,
                'server_type' => ServerType::Shared->value,
                'username' => 'demo@example.com',
                'purchase_date' => '2026-01-15',
                'expiry_date' => now()->addDays(9)->toDateString(),
                'price' => 89.00,
                'plan' => 'Business Shared',
                'description' => 'Fictional shared hosting record for product demonstrations.',
            ],
            [
                'title' => 'Demo Windows Server',
                'server_type' => ServerType::Dedicated->value,
                'ip_address' => '192.0.2.10',
                'username' => 'demo-admin',
                'expiry_date' => now()->addDays(21)->toDateString(),
                'price' => 240.00,
                'description' => 'Fictional Windows server using a TEST-NET address.',
            ],
            [
                'title' => 'Demo Ubuntu Server',
                'server_type' => ServerType::Dedicated->value,
                'ip_address' => '198.51.100.20',
                'username' => 'demo-operator',
                'expiry_date' => now()->addMonths(8)->toDateString(),
                'price' => 180.00,
                'description' => 'Fictional Linux server using a TEST-NET address.',
            ],
            [
                'title' => 'Demo File Server',
                'server_type' => ServerType::Dedicated->value,
                'ip_address' => '203.0.113.30',
                'username' => 'demo-user',
                'expiry_date' => now()->addMonths(14)->toDateString(),
                'price' => 120.00,
                'description' => 'Fictional file server using a TEST-NET address.',
            ],
            [
                'title' => 'Demo VMware RDP Host',
                'server_type' => ServerType::VMwareRdp->value,
                'ip_address' => '192.0.2.40',
                'username' => 'demo-rdp',
                'expiry_date' => now()->addDays(3)->toDateString(),
                'price' => 310.00,
                'description' => 'Fictional VMware RDP host using a TEST-NET address.',
            ],
        ];

        foreach ($records as $record) {
            Hosting::withTrashed()->updateOrCreate(
                ['title' => $record['title'], 'ip_address' => $record['ip_address'] ?? null],
                [...$record, 'status' => HostingStatus::Active->value],
            );
        }

        $domains = [
            [
                'name' => 'example.com',
                'expiry_date' => now()->addDays(12)->toDateString(),
                'price' => 14.99,
                'auto_renewal' => true,
                'whois_protection' => true,
            ],
            [
                'name' => 'example.net',
                'expiry_date' => now()->addMonths(7)->toDateString(),
                'price' => 12.50,
                'auto_renewal' => false,
                'whois_protection' => false,
            ],
        ];

        foreach ($domains as $domain) {
            Domain::withTrashed()->updateOrCreate(
                ['name' => $domain['name']],
                [
                    ...$domain,
                    'domain_type' => DomainType::Primary->value,
                    'status' => DomainStatus::Active->value,
                    'registrar_url' => 'https://registrar.example.com',
                    'expiry_notification' => true,
                    'notification_days_before' => 30,
                    'description' => 'Fictional domain record for product demonstrations.',
                ],
            );
        }
    }
}
