<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Agent;

use App\Contracts\Services\MonitoringServiceInterface;
use App\Enums\MetricType;
use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthenticateServerAgent;
use App\Http\Requests\Agent\HeartbeatRequest;
use App\Jobs\EvaluateServerMetrics;
use App\Models\Server;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

/**
 * Agent check-in.
 *
 * The busiest endpoint in the system: it runs once per server per interval, so
 * its cost is what ultimately bounds how many servers the platform can carry.
 * It therefore does the minimum — one insert, one targeted cursor update — and
 * hands threshold evaluation to a queued job so ingest latency stays flat as the
 * estate grows.
 *
 * There is no `{server}` in the URL: the server is whichever one the presented
 * token belongs to. An agent can only ever write to its own row, and cannot
 * enumerate or target another.
 */
class HeartbeatController extends Controller
{
    public function __construct(
        private readonly MonitoringServiceInterface $monitoring,
    ) {}

    public function store(HeartbeatRequest $request): JsonResponse
    {
        $server = $this->agentServer($request);
        $data = $request->validated();

        $heartbeat = $this->monitoring->recordHeartbeat($server, [
            ...$data,
            'source_ip' => $request->ip(),
        ]);

        // Metrics may ride along, so a constrained agent can report everything in
        // one request rather than two.
        $ingested = ['inserted' => 0, 'skipped' => 0];

        if (isset($data['metrics']) && is_array($data['metrics']) && $data['metrics'] !== []) {
            $ingested = $this->monitoring->recordMetrics($server, $data['metrics']);
        }

        // Out of band on purpose: see EvaluateServerMetrics.
        EvaluateServerMetrics::dispatch((int) $server->getKey());

        $interval = $server->heartbeat_interval_seconds
            ?: (int) Config::get('monitoring.heartbeat.default_interval_seconds', 60);

        return ApiResponse::success([
            'heartbeat_id' => (int) $heartbeat->getKey(),
            'received_at' => $heartbeat->received_at?->toIso8601String(),
            'metrics_accepted' => $ingested['inserted'],
            'metrics_skipped' => $ingested['skipped'],
            // Lets an agent self-tune: the schedule is server-driven, so an
            // operator can retune a noisy host without redeploying its agent.
            'next_interval_seconds' => (int) $interval,
        ], 'Heartbeat recorded.');
    }

    /**
     * What this agent should be doing.
     *
     * Exists so agent behaviour is configuration fetched at runtime rather than
     * baked into the agent build — the interval and the requested metric list can
     * change server-side and take effect on the agent's next poll.
     */
    public function config(Request $request): JsonResponse
    {
        $server = $this->agentServer($request);

        $interval = $server->heartbeat_interval_seconds
            ?: (int) Config::get('monitoring.heartbeat.default_interval_seconds', 60);

        return ApiResponse::success([
            'server' => [
                'id' => (int) $server->getKey(),
                'name' => $server->name,
                'os' => $server->os->value,
            ],
            'interval_seconds' => (int) $interval,
            'grace_seconds' => $server->offlineAfterSeconds(),
            // The catalogue, not a hardcoded list: a metric type added to the
            // enum is requested automatically.
            'metrics' => array_map(
                static fn (MetricType $type): array => [
                    'type' => $type->value,
                    'label' => $type->label(),
                    'unit' => $type->unit(),
                ],
                array_values(array_filter(
                    MetricType::cases(),
                    static fn (MetricType $type): bool => $type !== MetricType::Custom,
                )),
            ),
            // Unknown metric names are accepted and stored under `custom`, so an
            // agent may report more than the list above.
            'accepts_custom_metrics' => true,
            'max_batch_metrics' => (int) Config::get('monitoring.limits.max_batch_metrics', 200),
            'thresholds' => [
                'cpu_percent' => [
                    'warning' => $server->thresholdFor(MetricType::CpuPercent, 'warning'),
                    'critical' => $server->thresholdFor(MetricType::CpuPercent, 'critical'),
                ],
                'memory_percent' => [
                    'warning' => $server->thresholdFor(MetricType::MemoryPercent, 'warning'),
                    'critical' => $server->thresholdFor(MetricType::MemoryPercent, 'critical'),
                ],
                'disk_percent' => [
                    'warning' => $server->thresholdFor(MetricType::DiskPercent, 'warning'),
                    'critical' => $server->thresholdFor(MetricType::DiskPercent, 'critical'),
                ],
            ],
        ], 'Agent configuration.');
    }

    /**
     * The server the presented credential authenticates, as resolved by the
     * `agent` middleware. Never taken from user input.
     */
    private function agentServer(Request $request): Server
    {
        /** @var Server $server */
        $server = $request->attributes->get(AuthenticateServerAgent::SERVER_ATTRIBUTE);

        return $server;
    }
}
