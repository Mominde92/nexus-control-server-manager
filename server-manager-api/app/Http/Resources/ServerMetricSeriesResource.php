<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\MetricType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A bucketed metric series, ready to chart.
 *
 * The payload states the `source` (raw or rollup), the `bucket` width and whether
 * the result was `truncated` at the configured point cap. That is deliberate: the
 * client is told which resolution it actually received rather than assuming it
 * got every sample, so a 2-year chart and a 1-hour chart can be rendered
 * correctly by the same component.
 *
 * Thresholds travel with the series so the chart can draw its warning/critical
 * lines without a second request or a duplicated copy of the rules.
 *
 * @mixin array
 */
class ServerMetricSeriesResource extends JsonResource
{
    /**
     * @param  array<string, mixed>  $series
     */
    public function __construct(
        array $series,
        private readonly MetricType $type,
        private readonly ?float $warningThreshold = null,
        private readonly ?float $criticalThreshold = null,
    ) {
        parent::__construct($series);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        unset($request);

        /** @var array<string, mixed> $series */
        $series = $this->resource;

        return [
            'metric' => [
                'value' => $this->type->value,
                'label' => $this->type->label(),
                'unit' => $this->type->unit(),
                'is_percentage' => $this->type->isPercentage(),
            ],
            'source' => $series['source'] ?? 'raw',
            'bucket' => $series['bucket'] ?? null,
            'truncated' => (bool) ($series['truncated'] ?? false),
            'thresholds' => [
                'warning' => $this->warningThreshold,
                'critical' => $this->criticalThreshold,
            ],
            'points' => $series['points'] ?? [],
            'point_count' => is_array($series['points'] ?? null) ? count($series['points']) : 0,
        ];
    }
}
