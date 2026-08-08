<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Category of vendor. Examples: AWS/Azure/DigitalOcean (Cloud),
 * Cloudflare (CDN/DNS), Microsoft 365 / Google Workspace (Email).
 */
enum ProviderType: string
{
    use HasOptions;

    case Hosting = 'hosting';
    case DomainRegistrar = 'domain_registrar';
    case Cloud = 'cloud';
    case Cdn = 'cdn';
    case Dns = 'dns';
    case Email = 'email';
    case Ssl = 'ssl';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Hosting => 'Hosting',
            self::DomainRegistrar => 'Domain Registrar',
            self::Cloud => 'Cloud Platform',
            self::Cdn => 'CDN',
            self::Dns => 'DNS',
            self::Email => 'Email / Productivity',
            self::Ssl => 'SSL / Certificates',
            self::Other => 'Other',
        };
    }

    public function variant(): string
    {
        return 'secondary';
    }
}
