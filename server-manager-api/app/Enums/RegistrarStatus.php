<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Status of the domain at its registrar. Distinct from DomainStatus, which is
 * how *we* classify the record internally.
 */
enum RegistrarStatus: string
{
    use HasOptions;

    case Active = 'active';
    case Locked = 'locked';
    case PendingTransfer = 'pending_transfer';
    case Redemption = 'redemption';
    case Expired = 'expired';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Locked => 'Locked',
            self::PendingTransfer => 'Pending Transfer',
            self::Redemption => 'Redemption Period',
            self::Expired => 'Expired',
            self::Inactive => 'Inactive',
        };
    }

    public function variant(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Locked => 'info',
            self::PendingTransfer, self::Redemption => 'warning',
            self::Expired => 'destructive',
            self::Inactive => 'secondary',
        };
    }
}
