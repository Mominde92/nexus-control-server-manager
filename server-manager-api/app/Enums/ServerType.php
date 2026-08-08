<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ServerType: string
{
    use HasOptions;

    case Shared = 'shared';
    case Vps = 'vps';
    case Dedicated = 'dedicated';
    case Cloud = 'cloud';
    case Reseller = 'reseller';
    case Managed = 'managed';
    case Colocation = 'colocation';
    case VMwareRdp = 'vmware_rdp';

    public function label(): string
    {
        return match ($this) {
            self::Shared => 'Shared Hosting',
            self::Vps => 'VPS',
            self::Dedicated => 'Dedicated Server',
            self::Cloud => 'Cloud',
            self::Reseller => 'Reseller',
            self::Managed => 'Managed Hosting',
            self::Colocation => 'Colocation',
            self::VMwareRdp => 'VMware RDP',
        };
    }

    public function variant(): string
    {
        return 'secondary';
    }
}
