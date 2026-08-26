<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Services\MonitoringServiceInterface;
use Illuminate\Console\Command;

/**
 * Detects hosts that stopped reporting.
 *
 * An absence cannot be detected by ingest — a server that has gone down sends
 * nothing at all — so liveness has to be swept for. The sweep reads only the
 * denormalised `servers.last_heartbeat_at` cursor, never the heartbeat history,
 * which is what keeps it cheap enough to run every few minutes at any estate size.
 */
class CheckOfflineServers extends Command
{
    /**
     * @var string
     */
    protected $signature = 'monitoring:check-offline';

    /**
     * @var string
     */
    protected $description = 'Open or resolve offline alerts for servers that stopped reporting';

    public function handle(MonitoringServiceInterface $monitoring): int
    {
        $result = $monitoring->markOfflineServers();

        $this->info(sprintf(
            'Checked %d stale candidate(s): opened %d offline alert(s), resolved %d.',
            $result['checked'],
            $result['opened'],
            $result['resolved'],
        ));

        return self::SUCCESS;
    }
}
