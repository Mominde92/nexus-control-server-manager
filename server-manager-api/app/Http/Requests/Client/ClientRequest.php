<?php

declare(strict_types=1);

namespace App\Http\Requests\Client;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * Field constraints for the Client module, shared by Store and Update.
 */
abstract class ClientRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function fieldRules(): array
    {
        return [
            'name' => ['string', 'max:255'],
            'email' => ['string', 'email:rfc', 'max:255', $this->uniqueEmail()],
            'phone' => ['string', 'max:32'],
            'company' => ['string', 'max:255'],
            'website' => ['string', 'url:http,https', 'max:255'],
            'address' => ['string', 'max:255'],
            'notes' => ['string', 'max:65535'],
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function requiredFields(): array
    {
        return ['name'];
    }

    /**
     * Uniqueness is scoped to live rows: a soft-deleted client must not block a
     * new one from reusing its address.
     */
    private function uniqueEmail(): Unique
    {
        return Rule::unique('clients', 'email')
            ->whereNull('deleted_at')
            ->ignore($this->routeModelId());
    }

    protected function prepareForValidation(): void
    {
        $this->normaliseInput(lowercase: ['email']);

        $website = $this->input('website');

        if (is_string($website) && $website !== '' && preg_match('~^[a-z][a-z0-9+.-]*://~i', $website) !== 1) {
            $this->merge(['website' => 'https://'.ltrim($website, '/')]);
        }
    }
}
