<?php

declare(strict_types=1);

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Config;

/**
 * A batch of metric samples.
 *
 * Note what is deliberately NOT validated: `type` is checked only as a bounded
 * string, never against `MetricType::values()`. An unrecognised metric name is a
 * feature — the service stores it as `MetricType::Custom` with the original name
 * preserved — so rejecting it here would break the guarantee that a new agent
 * can report something the server has never heard of without a deploy.
 *
 * The batch size ceiling comes from config, so the ingest budget is an
 * operational knob rather than a constant in code.
 */
class IngestMetricsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $max = (int) Config::get('monitoring.limits.max_batch_metrics', 200);

        return [
            'metrics' => ['required', 'array', 'min:1', 'max:'.$max],
            'metrics.*.type' => ['required', 'string', 'max:64'],
            'metrics.*.key' => ['nullable', 'string', 'max:64'],
            'metrics.*.value' => ['required', 'numeric'],
            'metrics.*.recorded_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'metrics.max' => 'Too many samples in one batch; split the payload across requests.',
            'metrics.required' => 'Send at least one metric sample.',
        ];
    }
}
