<?php

declare(strict_types=1);

namespace App\Http\Requests\Agent;

use Illuminate\Foundation\Http\FormRequest;

/**
 * An agent check-in.
 *
 * Authorisation is the agent middleware's job (bearer token + monitoring
 * enabled); this class only validates shape. Rules are kept deliberately cheap
 * because this endpoint runs once per server per interval — the busiest request
 * in the system.
 *
 * `payload` is a free-form object on purpose: an agent that learns to report a
 * new fact (kernel build, container runtime) must not need an API change to send
 * it.
 */
class HeartbeatRequest extends FormRequest
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
        return [
            'agent_version' => ['nullable', 'string', 'max:64'],
            // Bounded to a plausible uptime rather than left open, so a garbled
            // reading cannot overflow the column.
            'uptime_seconds' => ['nullable', 'integer', 'min:0', 'max:4294967295'],

            'payload' => ['nullable', 'array'],

            // Metrics may ride along with the heartbeat, so a constrained agent
            // can report everything in a single request.
            'metrics' => ['nullable', 'array'],
            'metrics.*.type' => ['required_with:metrics', 'string', 'max:64'],
            'metrics.*.key' => ['nullable', 'string', 'max:64'],
            'metrics.*.value' => ['required_with:metrics', 'numeric'],
            'metrics.*.recorded_at' => ['nullable', 'date'],
        ];
    }
}
