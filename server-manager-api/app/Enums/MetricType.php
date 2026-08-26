<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Metric kinds the platform understands.
 *
 * Deliberately an open-ended *catalogue*, not a fixed column set: metrics are
 * stored as (server_id, metric_type, recorded_at, value) rows, so adding a new
 * metric means adding a case here — never an ALTER TABLE. Agents may also send
 * a metric key not listed here; ingest keeps it under `Custom` with the
 * original key preserved.
 */
enum MetricType: string
{
    use HasOptions;

    case CpuPercent = 'cpu_percent';
    case MemoryPercent = 'memory_percent';
    case DiskPercent = 'disk_percent';
    case SwapPercent = 'swap_percent';
    case LoadAverage = 'load_average';
    case NetworkInKbps = 'network_in_kbps';
    case NetworkOutKbps = 'network_out_kbps';
    case UptimeSeconds = 'uptime_seconds';
    case ProcessCount = 'process_count';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::CpuPercent => 'CPU',
            self::MemoryPercent => 'Memory',
            self::DiskPercent => 'Disk',
            self::SwapPercent => 'Swap',
            self::LoadAverage => 'Load average',
            self::NetworkInKbps => 'Network in',
            self::NetworkOutKbps => 'Network out',
            self::UptimeSeconds => 'Uptime',
            self::ProcessCount => 'Processes',
            self::Custom => 'Custom',
        };
    }

    public function variant(): string
    {
        return 'secondary';
    }

    public function unit(): string
    {
        return match ($this) {
            self::CpuPercent, self::MemoryPercent,
            self::DiskPercent, self::SwapPercent => '%',
            self::LoadAverage => '',
            self::NetworkInKbps, self::NetworkOutKbps => 'kbps',
            self::UptimeSeconds => 's',
            self::ProcessCount => '',
            self::Custom => '',
        };
    }

    /**
     * Metrics expressed as a 0–100 percentage can share default thresholds and
     * a common chart axis.
     */
    public function isPercentage(): bool
    {
        return in_array($this, [
            self::CpuPercent,
            self::MemoryPercent,
            self::DiskPercent,
            self::SwapPercent,
        ], true);
    }

    /**
     * Default warning / critical thresholds for percentage metrics. Per-server
     * overrides live on the `servers` row, so these are only fallbacks.
     *
     * @return array{warning: float, critical: float}|null
     */
    public function defaultThresholds(): ?array
    {
        return $this->isPercentage()
            ? ['warning' => 80.0, 'critical' => 92.0]
            : null;
    }
}
