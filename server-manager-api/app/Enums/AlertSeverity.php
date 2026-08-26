<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum AlertSeverity: string
{
    use HasOptions;

    case Info = 'info';
    case Warning = 'warning';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Info => 'Info',
            self::Warning => 'Warning',
            self::Critical => 'Critical',
        };
    }

    public function variant(): string
    {
        return match ($this) {
            self::Info => 'info',
            self::Warning => 'warning',
            self::Critical => 'destructive',
        };
    }

    public function severity(): int
    {
        return match ($this) {
            self::Critical => 0,
            self::Warning => 1,
            self::Info => 2,
        };
    }
}
