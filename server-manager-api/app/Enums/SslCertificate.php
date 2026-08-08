<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Kind of TLS certificate installed on the hosting account.
 */
enum SslCertificate: string
{
    use HasOptions;

    case None = 'none';
    case LetsEncrypt = 'lets_encrypt';
    case Commercial = 'commercial';
    case Wildcard = 'wildcard';
    case SelfSigned = 'self_signed';
    case CloudflareManaged = 'cloudflare_managed';

    public function label(): string
    {
        return match ($this) {
            self::None => 'None',
            self::LetsEncrypt => "Let's Encrypt",
            self::Commercial => 'Commercial',
            self::Wildcard => 'Wildcard',
            self::SelfSigned => 'Self-signed',
            self::CloudflareManaged => 'Cloudflare Managed',
        };
    }

    public function variant(): string
    {
        return match ($this) {
            self::None => 'destructive',
            self::SelfSigned => 'warning',
            self::LetsEncrypt, self::Commercial, self::Wildcard, self::CloudflareManaged => 'success',
        };
    }
}
