<?php

declare(strict_types=1);

namespace App\Http\Requests\Server;

use App\Enums\ServerOs;
use App\Http\Requests\Concerns\BuildsFieldRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Field contract for a server, shared by create and update.
 *
 * Declared once here so the two endpoints cannot drift: the Store/Update
 * subclasses contribute only their presence strategy. Authorisation lives in
 * ServerPolicy, invoked from the controller, so a denial answers 403 rather than
 * a validation error.
 */
abstract class ServerRequest extends FormRequest
{
    use BuildsFieldRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<int, string>
     */
    protected function requiredFields(): array
    {
        return ['name'];
    }

    /**
     * Value rules only — presence comes from the subclass.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function valueRules(): array
    {
        return [
            'name' => ['string', 'max:255'],
            'hostname' => ['string', 'max:255'],
            'ip_address' => ['ip', 'max:45'],

            // Membership is derived from the enum, so a newly supported OS is
            // accepted the moment its case exists — no rule to update here.
            'os' => ['string', Rule::in(ServerOs::values())],
            'os_version' => ['string', 'max:255'],

            // Soft-deleted parents are excluded: linking to a trashed record
            // would produce a row the UI cannot render.
            'hosting_id' => ['integer', Rule::exists('hostings', 'id')->whereNull('deleted_at')],
            'client_id' => ['integer', Rule::exists('clients', 'id')->whereNull('deleted_at')],
            'project_id' => ['integer', Rule::exists('projects', 'id')->whereNull('deleted_at')],

            'monitoring_enabled' => ['boolean'],

            // Bounded to keep a typo from either hammering the API every second
            // or silently disabling liveness detection for an hour.
            'heartbeat_interval_seconds' => ['integer', 'between:10,3600'],
            'heartbeat_grace_seconds' => ['integer', 'between:10,3600'],

            // Percentage thresholds. `gte` pairs are only comparable when both
            // halves are in the payload, so the rule is added conditionally —
            // otherwise a PATCH of just the warning value would fail.
            'cpu_warning_threshold' => ['integer', 'between:1,100'],
            'cpu_critical_threshold' => [
                'integer',
                'between:1,100',
                ...($this->filled('cpu_warning_threshold') ? ['gte:cpu_warning_threshold'] : []),
            ],
            'memory_warning_threshold' => ['integer', 'between:1,100'],
            'memory_critical_threshold' => [
                'integer',
                'between:1,100',
                ...($this->filled('memory_warning_threshold') ? ['gte:memory_warning_threshold'] : []),
            ],
            'disk_warning_threshold' => ['integer', 'between:1,100'],
            'disk_critical_threshold' => [
                'integer',
                'between:1,100',
                ...($this->filled('disk_warning_threshold') ? ['gte:disk_warning_threshold'] : []),
            ],

            'notes' => ['string', 'max:5000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->trimStrings($this->all()));
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'os.in' => 'Choose a supported operating system.',
            'ip_address.ip' => 'Enter a valid IPv4 or IPv6 address.',
            'hosting_id.exists' => 'The selected hosting account no longer exists.',
            'client_id.exists' => 'The selected client no longer exists.',
            'project_id.exists' => 'The selected project no longer exists.',
            'heartbeat_interval_seconds.between' => 'Heartbeat interval must be between 10 and 3600 seconds.',
            'heartbeat_grace_seconds.between' => 'Heartbeat grace must be between 10 and 3600 seconds.',
            'cpu_critical_threshold.gte' => 'The CPU critical threshold cannot be lower than the warning threshold.',
            'memory_critical_threshold.gte' => 'The memory critical threshold cannot be lower than the warning threshold.',
            'disk_critical_threshold.gte' => 'The disk critical threshold cannot be lower than the warning threshold.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'os' => 'operating system',
            'ip_address' => 'IP address',
            'hosting_id' => 'hosting account',
            'client_id' => 'client',
            'project_id' => 'project',
            'heartbeat_interval_seconds' => 'heartbeat interval',
            'heartbeat_grace_seconds' => 'heartbeat grace',
            'cpu_warning_threshold' => 'CPU warning threshold',
            'cpu_critical_threshold' => 'CPU critical threshold',
            'memory_warning_threshold' => 'memory warning threshold',
            'memory_critical_threshold' => 'memory critical threshold',
            'disk_warning_threshold' => 'disk warning threshold',
            'disk_critical_threshold' => 'disk critical threshold',
        ];
    }
}
