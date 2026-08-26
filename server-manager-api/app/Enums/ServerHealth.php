<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Live health of a monitored server, derived from heartbeat recency and the
 * latest metric sample — never stored as a mutable "current state" that could
 * drift out of sync with reality.
 */
enum ServerHealth: string
{
    use HasOptions;

    /** Heartbeat received within the expected interval, metrics nominal. */
    case Healthy = 'healthy';

    /** Reporting, but a metric crossed a warning threshold. */
    case Warning = 'warning';

    /** Reporting, but a metric crossed a critical threshold. */
    case Critical = 'critical';

    /** No heartbeat within the grace window — agent down or host unreachable. */
    case Offline = 'offline';

    /** Monitoring enabled but no heartbeat has ever arrived. */
    case Pending = 'pending';

    /** Monitoring switched off for this server. */
    case Disabled = 'disabled';

    public function label(): string
    {
        return match ($this) {
            self::Healthy => 'Healthy',
            self::Warning => 'Warning',
            self::Critical => 'Critical',
            self::Offline => 'Offline',
            self::Pending => 'Awaiting first heartbeat',
            self::Disabled => 'Monitoring disabled',
        };
    }

    public function variant(): string
    {
        return match ($this) {
            self::Healthy => 'success',
            self::Warning => 'warning',
            self::Critical, self::Offline => 'destructive',
            self::Pending => 'info',
            self::Disabled => 'secondary',
        };
    }

    /**
     * Ordering for "worst first" dashboard sorting.
     */
    public function severity(): int
    {
        return match ($this) {
            self::Critical => 0,
            self::Offline => 1,
            self::Warning => 2,
            self::Pending => 3,
            self::Healthy => 4,
            self::Disabled => 5,
        };
    }

    public function needsAttention(): bool
    {
        return in_array($this, [self::Critical, self::Offline, self::Warning], true);
    }
}
