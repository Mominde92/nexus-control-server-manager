<?php

declare(strict_types=1);

namespace App\Http\Requests\Project;

use App\Enums\ProjectStatus;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

/**
 * Field constraints for the Project module, shared by Store and Update.
 */
abstract class ProjectRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function fieldRules(): array
    {
        return [
            'name' => ['string', 'max:255'],
            'description' => ['string', 'max:65535'],
            'status' => ['string', Rule::in(ProjectStatus::values())],

            // A trashed client must not be assignable.
            'client_id' => [
                'integer',
                Rule::exists('clients', 'id')->whereNull('deleted_at'),
            ],

            'start_date' => ['date'],

            // Only cross-check the pair when a start date was actually
            // submitted; on a partial update that omits it, comparing against a
            // missing field would compare against nothing.
            'end_date' => $this->filled('start_date')
                ? ['date', 'after_or_equal:start_date']
                : ['date'],
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function requiredFields(): array
    {
        return ['name', 'status'];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'client_id' => 'client',
            'start_date' => 'start date',
            'end_date' => 'end date',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'end_date.after_or_equal' => 'The end date must fall on or after the start date.',
            'client_id.exists' => 'The selected client no longer exists.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->normaliseInput();
    }
}
