<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\Services\MonitoringServiceInterface;
use App\Models\Server;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Evaluates one server's thresholds out of band.
 *
 * Why this is a job and not an inline call: ingest runs once per server per
 * heartbeat interval, and it is the one request whose latency directly limits how
 * many servers the platform can carry. Threshold evaluation reads the latest
 * sample per metric and may write alerts, so doing it inline would add that cost
 * to every check-in. Dispatching it keeps ingest at "insert and acknowledge",
 * which is flat regardless of estate size.
 *
 * `ShouldBeUnique` collapses bursts: an agent that posts a heartbeat and a metric
 * batch back to back queues one evaluation, not two.
 */
class EvaluateServerMetrics implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Alerting is best-effort telemetry, not a financial transaction: retry a
     * couple of times, then let it go rather than piling up a backlog.
     */
    public int $tries = 3;

    public int $backoff = 10;

    /**
     * Uniqueness window. Slightly longer than the shortest sane interval so a
     * chatty agent cannot enqueue an evaluation per request.
     */
    public int $uniqueFor = 30;

    public function __construct(
        public readonly int $serverId,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->serverId;
    }

    public function handle(MonitoringServiceInterface $monitoring): void
    {
        $server = Server::query()->find($this->serverId);

        // The server may have been deleted, or monitoring switched off, between
        // ingest and this job running. Neither is an error.
        if ($server === null || ! $server->monitoring_enabled) {
            return;
        }

        $monitoring->evaluateThresholds($server);
    }
}
