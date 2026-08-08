<?php

declare(strict_types=1);

namespace App\Http\Requests\Domain;

use App\Enums\DomainStatus;
use App\Enums\DomainType;
use App\Enums\RegistrarStatus;
use App\Http\Requests\Concerns\BuildsFieldRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * Field contract for a domain record, shared by create and update.
 */
abstract class DomainRequest extends FormRequest
{
    use BuildsFieldRules;

    /**
     * A hostname label is 1–63 characters and a full name is at most 253.
     * Punycode (`xn--…`) is accepted, so IDNs work once encoded.
     */
    private const string NAME_PATTERN = '/^(?!-)[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.(?!-)[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)+$/';

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
     * @return array<string, array<int, mixed>>
     */
    protected function valueRules(): array
    {
        return [
            'name' => [
                'string',
                'lowercase',
                'max:253',
                'regex:'.self::NAME_PATTERN,
                $this->uniqueNameRule(),
            ],

            'provider_id' => ['integer', Rule::exists('providers', 'id')->whereNull('deleted_at')],
            'provider_name' => ['string', 'max:255'],
            'provider_url' => ['url:http,https', 'max:255'],

            'domain_type' => ['string', Rule::in(DomainType::values())],
            'hosting_id' => ['integer', Rule::exists('hostings', 'id')->whereNull('deleted_at')],

            'username' => ['string', 'max:255'],
            'password' => ['string', 'max:255'],

            'purchase_date' => ['date', 'date_format:Y-m-d'],
            'expiry_date' => [
                'date',
                'date_format:Y-m-d',
                ...($this->filled('purchase_date') ? ['after_or_equal:purchase_date'] : []),
            ],

            'price' => ['numeric', 'min:0', 'max:99999999.99'],
            'currency' => ['string', 'size:3', 'alpha'],
            'plan' => ['string', 'max:255'],

            'registrar_url' => ['url:http,https', 'max:255'],
            'registrar_username' => ['string', 'max:255'],
            'registrar_password' => ['string', 'max:255'],
            'registrar_status' => ['string', Rule::in(RegistrarStatus::values())],

            'project_id' => ['integer', Rule::exists('projects', 'id')->whereNull('deleted_at')],
            'client_id' => ['integer', Rule::exists('clients', 'id')->whereNull('deleted_at')],

            'status' => ['string', Rule::in(DomainStatus::values())],

            'auto_renewal' => ['boolean'],
            'whois_protection' => ['boolean'],

            'expiry_notification' => ['boolean'],
            'notification_days_before' => ['integer', 'between:1,365'],

            'description' => ['string', 'max:5000'],
        ];
    }

    /**
     * Uniqueness over live rows only: a soft-deleted record must not block
     * re-registering the same name. UpdateDomainRequest widens this to ignore
     * the record being edited.
     */
    protected function uniqueNameRule(): Unique
    {
        return Rule::unique('domains', 'name')->whereNull('deleted_at');
    }

    /**
     * DNS is case-insensitive, so the canonical form is lower case — applied
     * before validation so the `lowercase` and `unique` rules see the value
     * that will actually be stored.
     */
    protected function prepareForValidation(): void
    {
        $input = $this->trimStrings($this->all());

        if (isset($input['name']) && is_string($input['name'])) {
            $input['name'] = mb_strtolower($input['name']);
        }

        $this->merge($input);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.regex' => 'Enter a valid domain name, for example example.com.',
            'name.unique' => 'That domain is already recorded.',
            'name.max' => 'A domain name cannot exceed 253 characters.',
            'provider_id.exists' => 'The selected provider no longer exists.',
            'hosting_id.exists' => 'The selected hosting account no longer exists.',
            'project_id.exists' => 'The selected project no longer exists.',
            'client_id.exists' => 'The selected client no longer exists.',
            'currency.size' => 'Use a three-letter currency code, for example USD.',
            'currency.alpha' => 'Use a three-letter currency code, for example USD.',
            'expiry_date.after_or_equal' => 'The expiry date cannot fall before the purchase date.',
            'notification_days_before.between' => 'Remind between 1 and 365 days before expiry.',
            'status.in' => 'Choose a valid domain status.',
            'domain_type.in' => 'Choose a valid domain type.',
            'registrar_status.in' => 'Choose a valid registrar status.',
            'price.max' => 'The price is too large to store.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'domain name',
            'provider_id' => 'provider',
            'project_id' => 'project',
            'client_id' => 'client',
            'hosting_id' => 'hosting account',
            'registrar_url' => 'registrar URL',
            'whois_protection' => 'WHOIS privacy',
            'auto_renewal' => 'auto-renewal',
            'notification_days_before' => 'reminder window',
            'expiry_notification' => 'expiry reminders',
        ];
    }
}
