<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum HostingStatus: string
{
    use HasOptions;

    case Active = 'active';
    case Pending = 'pending';
    case Suspended = 'suspended';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Pending => 'Pending',
            self::Suspended => 'Suspended',
            self::Expired => 'Expired',
        };
    }

    public function variant(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Pending => 'warning',
            self::Suspended => 'destructive',
            self::Expired => 'secondary',
        };
    }
}
