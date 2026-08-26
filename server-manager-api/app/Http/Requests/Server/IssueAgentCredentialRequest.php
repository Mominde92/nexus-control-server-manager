<?php

declare(strict_types=1);

namespace App\Http\Requests\Server;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Issue or rotate an agent credential.
 *
 * The only accepted input is a human label. The token id and secret are
 * generated server-side — a caller must never be able to influence, supply or
 * predict either half.
 */
class IssueAgentCredentialRequest extends FormRequest
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
            'name' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $name = $this->input('name');

        if (is_string($name)) {
            $trimmed = trim($name);
            $this->merge(['name' => $trimmed === '' ? null : $trimmed]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'credential label'];
    }
}
