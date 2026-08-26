<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Agent;

use App\Contracts\Services\MonitoringServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthenticateServerAgent;
use App\Http\Requests\Agent\IngestMetricsRequest;
use App\Jobs\EvaluateServerMetrics;
use App\Models\Server;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Metric ingest.
 *
 * A batch becomes ONE bulk insert (see MonitoringService::recordMetrics), and
 * threshold evaluation is dispatched rather than run inline. Unrecognised metric
 * names are stored under `MetricType::Custom` with the original name kept in
 * `metric_key`, so an agent reporting something new needs neither a schema change
 * nor an API change.
 *
 * As with heartbeats, the target server comes from the token, never the URL.
 */
class MetricController extends Controller
{
    public function __construct(
        private readonly MonitoringServiceInterface $monitoring,
    ) {}

    public function store(IngestMetricsRequest $request): JsonResponse
    {
        /** @var Server $server */
        $server = $request->attributes->get(AuthenticateServerAgent::SERVER_ATTRIBUTE);

        /** @var array<int, array<string, mixed>> $metrics */
        $metrics = $request->validated()['metrics'];

        $result = $this->monitoring->recordMetrics($server, $metrics);

        EvaluateServerMetrics::dispatch((int) $server->getKey());

        return ApiResponse::success([
            'accepted' => $result['inserted'],
            'skipped' => $result['skipped'],
        ], 'Metrics recorded.');
    }
}
