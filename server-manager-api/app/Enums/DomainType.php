<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * How the domain is used within the estate.
 */
enum DomainType: string
{
    use HasOptions;

    case Primary = 'primary';
    case Addon = 'addon';
    case Subdomain = 'subdomain';
    case Parked = 'parked';
    case Redirect = 'redirect';

    public function label(): string
    {
        return match ($this) {
            self::Primary => 'Primary',
            self::Addon => 'Add-on',
            self::Subdomain => 'Subdomain',
            self::Parked => 'Parked',
            self::Redirect => 'Redirect',
        };
    }

    public function variant(): string
    {
        return match ($this) {
            self::Primary => 'default',
            self::Addon, self::Subdomain => 'info',
            self::Parked, self::Redirect => 'secondary',
        };
    }
}
