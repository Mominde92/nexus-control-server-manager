<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\Repositories\ServerMetricRepositoryInterface;
use App\Enums\MetricType;
use App\Http\Controllers\Controller;
use App\Http\Resources\ServerMetricSeriesResource;
use App\Models\Server;
use App\Support\ApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Metric reads for charting.
 *
 * The requested window is always bounded and always resolved through the
 * repository, which picks raw or rollup data and caps the point count from
 * config. A client cannot ask for "everything": the range is clamped, the
 * resolution is chosen for it, and the response states what it actually got.
 */
class ServerMetricController extends Controller
{
    use AuthorizesRequests;

    /**
     * Longest window a single request may span. Two years matches the daily
     * rollup retention — asking for more would return empty buckets.
     */
    private const int MAX_RANGE_HOURS = 24 * 730;

    public function __construct(
        private readonly ServerMetricRepositoryInterface $metrics,
    ) {}

    /**
     * A bucketed series for one metric.
     */
    public function series(Request $request, Server $server): JsonResponse
    {
        $this->authorize('viewMetrics', $server);

        $validated = $request->validate([
            'metric' => ['nullable', 'string'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'hours' => ['nullable', 'integer', 'between:1,'.self::MAX_RANGE_HOURS],
        ]);

        $type = MetricType::tryFromValue($validated['metric'] ?? null) ?? MetricType::CpuPercent;

        [$from, $to] = $this->resolveRange($validated);

        $series = $this->metrics->seriesFor($server, $type, $from, $to);

        return ApiResponse::resource(
            new ServerMetricSeriesResource(
                $series,
                $type,
                $server->thresholdFor($type, 'warning'),
                $server->thresholdFor($type, 'critical'),
            ),
        );
    }

    /**
     * The most recent sample per metric type — the "current values" panel.
     *
     * One query for every type, not one per type.
     */
    public function latest(Request $request, Server $server): JsonResponse
    {
        $this->authorize('viewMetrics', $server);

        $requested = $request->input('metrics');
        $types = [];

        if (is_string($requested) && $requested !== '') {
            foreach (explode(',', $requested) as $candidate) {
                $type = MetricType::tryFromValue(trim($candidate));

                if ($type !== null) {
                    $types[] = $type;
                }
            }
        }

        $latest = $this->metrics->latestFor($server, $types);

        return ApiResponse::success([
            'server_id' => (int) $server->getKey(),
            'metrics' => $this->decorate($latest),
        ], 'Latest metrics.');
    }

    /**
     * Attach the label, unit and effective thresholds to each reading, so the UI
     * does not duplicate the enum's vocabulary or the threshold rules.
     *
     * @param  array<string, array{value: float, recorded_at: string, metric_key: string|null}>  $latest
     * @return array<int, array<string, mixed>>
     */
    private function decorate(array $latest): array
    {
        $decorated = [];

        foreach ($latest as $value => $sample) {
            $type = MetricType::tryFromValue($value);

            if ($type === null) {
                continue;
            }

            $decorated[] = [
                'type' => $type->value,
                'label' => $type->label(),
                'unit' => $type->unit(),
                'is_percentage' => $type->isPercentage(),
                'metric_key' => $sample['metric_key'],
                'value' => $sample['value'],
                'recorded_at' => $sample['recorded_at'],
            ];
        }

        return $decorated;
    }

    /**
     * Resolve the requested window, clamped to the maximum span.
     *
     * `hours` is the convenient form ("last 24h"); explicit `from`/`to` wins when
     * both are supplied. Either way the result is bounded, because an unbounded
     * range is the one input that could make this query expensive.
     *
     * @param  array<string, mixed>  $validated
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveRange(array $validated): array
    {
        $to = isset($validated['to']) ? Carbon::parse((string) $validated['to']) : now();
        $from = isset($validated['from'])
            ? Carbon::parse((string) $validated['from'])
            : $to->copy()->subHours((int) ($validated['hours'] ?? 24));

        if ($from->greaterThanOrEqualTo($to)) {
            $from = $to->copy()->subHours(24);
        }

        // Clamp rather than reject: a client asking for too much gets the newest
        // slice it is allowed, not an error.
        if ($from->diffInHours($to) > self::MAX_RANGE_HOURS) {
            $from = $to->copy()->subHours(self::MAX_RANGE_HOURS);
        }

        return [$from, $to];
    }
}
