<?php

declare(strict_types=1);

namespace App\Http\Requests\Provider;

use App\Enums\ProviderStatus;
use App\Enums\ProviderType;
use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

/**
 * Field constraints for the Provider module, shared by Store and Update.
 */
abstract class ProviderRequest extends ApiFormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function fieldRules(): array
    {
        return [
            'name' => ['string', 'max:255'],
            'url' => ['string', 'url:http,https', 'max:255'],
            'type' => ['string', Rule::in(ProviderType::values())],
            'status' => ['string', Rule::in(ProviderStatus::values())],
            'support_email' => ['string', 'email:rfc', 'max:255'],
            'support_phone' => ['string', 'max:32'],
            'notes' => ['string', 'max:65535'],
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function requiredFields(): array
    {
        return ['name', 'type'];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'url' => 'website URL',
            'support_email' => 'support email',
            'support_phone' => 'support phone',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->normaliseInput(lowercase: ['support_email']);

        // Accept a bare domain in the URL field; the service canonicalises it,
        // but the `url` rule needs a scheme to pass.
        $url = $this->input('url');

        if (is_string($url) && $url !== '' && preg_match('~^[a-z][a-z0-9+.-]*://~i', $url) !== 1) {
            $this->merge(['url' => 'https://'.ltrim($url, '/')]);
        }
    }
}
