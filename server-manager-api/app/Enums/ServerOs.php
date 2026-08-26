<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Operating-system family of a monitored server.
 *
 * This is a *label plus agent-selection hint*, never a branch point for
 * monitoring logic: the ingest pipeline, storage and alerting are all
 * OS-agnostic. Supporting a new OS means adding a case here and shipping an
 * agent that speaks the same ingest contract — no schema change, no changes to
 * the monitoring module.
 *
 * `Other` exists so an unrecognised platform is still monitorable today.
 */
enum ServerOs: string
{
    use HasOptions;

    case Windows = 'windows';
    case Linux = 'linux';
    case MacOs = 'macos';
    case FreeBsd = 'freebsd';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Windows => 'Windows Server',
            self::Linux => 'Linux',
            self::MacOs => 'macOS',
            self::FreeBsd => 'FreeBSD',
            self::Other => 'Other',
        };
    }

    public function variant(): string
    {
        return 'secondary';
    }

    /**
     * Identifier of the agent flavour that ships for this platform. Consumed by
     * the install-instructions endpoint; unknown platforms fall back to the
     * portable agent.
     */
    public function agentFlavour(): string
    {
        return match ($this) {
            self::Windows => 'windows-powershell',
            self::Linux, self::FreeBsd => 'posix-shell',
            self::MacOs => 'posix-shell',
            self::Other => 'portable',
        };
    }
}
